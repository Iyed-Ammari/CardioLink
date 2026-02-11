<?php

namespace App\Controller;

use App\Entity\Ordonnance;
use App\Entity\RendezVous;
use App\Form\OrdonnanceType;
use App\Repository\OrdonnanceRepository;
use App\Repository\RendezVousRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/ordonnance')]
#[IsGranted('ROLE_MEDECIN')]
class OrdonnanceController extends AbstractController
{
    #[Route('/', name: 'ordonnance_index', methods: ['GET'])]
    public function index(OrdonnanceRepository $repository): Response
    {
        $ordonnances = $repository->findAll();
        return $this->render('ordonnance/index.html.twig', compact('ordonnances'));
    }

    #[Route('/new', name: 'ordonnance_new', methods: ['GET','POST'])]
    public function new(Request $request, EntityManagerInterface $em, RendezVousRepository $rdvRepository): Response
    {
        $ordonnance = new Ordonnance();
        
        // Récupérer le rdvId depuis les paramètres
        $rdvId = $request->query->get('rdvId');
        $rdv = null;
        
        if ($rdvId) {
            $rdv = $rdvRepository->find($rdvId);
            
            // Vérifier que le RDV appartient au médecin connecté
            if (!$rdv || $rdv->getMedecin() !== $this->getUser()) {
                $this->addFlash('danger', 'Accès non autorisé à ce rendez-vous.');
                return $this->redirectToRoute('ordonnance_index');
            }
            
            // Initialiser automatiquement les données du patient et du médecin
            $ordonnance->setPatientNom($rdv->getPatient()->getNom() . ' ' . $rdv->getPatient()->getPrenom());
            $ordonnance->setMedecinNom($rdv->getMedecin()->getNom() . ' ' . $rdv->getMedecin()->getPrenom());
            $ordonnance->setConsultation($rdv);
        }
        
        $form = $this->createForm(OrdonnanceType::class, $ordonnance);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($ordonnance);
            $em->flush();
            return $this->redirectToRoute('ordonnance_index');
        }

        return $this->render('ordonnance/new.html.twig', [
            'form' => $form->createView(),
            'rdv' => $rdv,
        ]);
    }

    #[Route('/{id}/edit', name: 'ordonnance_edit', methods: ['GET','POST'])]
    public function edit(Request $request, Ordonnance $ordonnance, EntityManagerInterface $em): Response
    {
        // Vérifier que l'ordonnance appartient au médecin connecté
        if ($ordonnance->getConsultation() && $ordonnance->getConsultation()->getMedecin() !== $this->getUser()) {
            $this->addFlash('danger', 'Vous n\'avez pas accès à cette ordonnance.');
            return $this->redirectToRoute('ordonnance_index');
        }
        
        $form = $this->createForm(OrdonnanceType::class, $ordonnance);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            return $this->redirectToRoute('ordonnance_index');
        }

        return $this->render('ordonnance/edit.html.twig', [
            'form' => $form->createView(),
            'ordonnance' => $ordonnance,
        ]);
    }

    #[Route('/{id}', name: 'ordonnance_delete', methods: ['POST'])]
    public function delete(Request $request, Ordonnance $ordonnance, EntityManagerInterface $em): Response
    {
        // Vérifier que l'ordonnance appartient au médecin connecté
        if ($ordonnance->getConsultation() && $ordonnance->getConsultation()->getMedecin() !== $this->getUser()) {
            $this->addFlash('danger', 'Vous n\'avez pas accès à cette ordonnance.');
            return $this->redirectToRoute('ordonnance_index');
        }
        
        if ($this->isCsrfTokenValid('delete'.$ordonnance->getId(), $request->request->get('_token'))) {
            $em->remove($ordonnance);
            $em->flush();
        }

        return $this->redirectToRoute('ordonnance_index');
    }
    
    #[Route('/{id}', name: 'ordonnance_show', methods: ['GET'])]
    public function show(Ordonnance $ordonnance): Response
    {
        // Vérifier que l'ordonnance appartient au médecin connecté
        if ($ordonnance->getConsultation() && $ordonnance->getConsultation()->getMedecin() !== $this->getUser()) {
            $this->addFlash('danger', 'Vous n\'avez pas accès à cette ordonnance.');
            return $this->redirectToRoute('ordonnance_index');
        }
        
        return $this->render('ordonnance/show.html.twig', [
            'ordonnance' => $ordonnance,
        ]);
    }
}
