<?php

namespace App\Controller;

use App\Entity\Ordonnance;
use App\Entity\RendezVous;
use App\Entity\User;
use App\Form\OrdonnanceType;
use App\Repository\OrdonnanceRepository;
use App\Repository\RendezVousRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\HttpClient\HttpClient;

#[Route('/ordonnance')]
class OrdonnanceController extends AbstractController
{
    #[Route('/', name: 'ordonnance_index', methods: ['GET'])]
    public function index(OrdonnanceRepository $repository): Response
    {
        return $this->render('ordonnance/index.html.twig', [
            'ordonnances' => $repository->findAll(),
        ]);
    }

    #[Route('/new', name: 'ordonnance_new', methods: ['GET','POST'])]
    public function new(Request $request, EntityManagerInterface $em, RendezVousRepository $rdvRepository): Response
    {
        $rdvId = $request->query->get('rdvId');
        $rdv = $rdvRepository->find($rdvId);

        if (!$rdv) {
            $this->addFlash('danger', 'Rendez-vous introuvable.');
            return $this->redirectToRoute('app_rdv_index');
        }

        $user = $this->getUser();
        if (!$user instanceof User || $rdv->getMedecin()?->getId() !== $user->getId()) {
            $this->addFlash('danger', 'Accès non autorisé à ce rendez-vous.');
            return $this->redirectToRoute('app_rdv_index');
        }

        // Vérifier si ordonnance existe déjà
        if ($rdv->getOrdonnance()) {
            return $this->redirectToRoute('ordonnance_show', ['id' => $rdv->getOrdonnance()->getId()]);
        }

        $ordonnance = new Ordonnance();
        $ordonnance->setPatientNom($rdv->getPatient()->getNom() . ' ' . $rdv->getPatient()->getPrenom());
        $ordonnance->setMedecinNom($rdv->getMedecin()->getNom() . ' ' . $rdv->getMedecin()->getPrenom());
        $ordonnance->setConsultation($rdv);

        $form = $this->createForm(OrdonnanceType::class, $ordonnance);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($ordonnance);
            $em->flush();

            $this->addFlash('success', 'Ordonnance créée avec succès');
            return $this->redirectToRoute('ordonnance_show', ['id' => $ordonnance->getId()]);
        }

        return $this->render('ordonnance/new.html.twig', [
            'form' => $form->createView(),
            'rdv' => $rdv
        ]);
    }

    #[Route('/{id}/show', name: 'ordonnance_show', methods: ['GET'])]
    public function show(Ordonnance $ordonnance): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User ) {
            $this->addFlash('danger', 'Vous n\'avez pas accès à cette ordonnance.');
            return $this->redirectToRoute('app_rdv_index');
        }

        return $this->render('ordonnance/show.html.twig', [
            'ordonnance' => $ordonnance,
        ]);
    }

    #[Route('/{id}/edit', name: 'ordonnance_edit', methods: ['GET','POST'])]
    public function edit(Request $request, Ordonnance $ordonnance, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User || $ordonnance->getConsultation()?->getMedecin()?->getId() !== $user->getId()) {
            $this->addFlash('danger', 'Vous n\'avez pas accès à cette ordonnance.');
            return $this->redirectToRoute('app_rdv_index');
        }

        $form = $this->createForm(OrdonnanceType::class, $ordonnance);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Ordonnance mise à jour');
            return $this->redirectToRoute('ordonnance_show', ['id' => $ordonnance->getId()]);
        }

        return $this->render('ordonnance/edit.html.twig', [
            'form' => $form->createView(),
            'ordonnance' => $ordonnance
        ]);
    }

    #[Route('/{id}/delete', name: 'ordonnance_delete', methods: ['POST'])]
    public function delete(Request $request, Ordonnance $ordonnance, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User || $ordonnance->getConsultation()?->getMedecin()?->getId() !== $user->getId()) {
            $this->addFlash('danger', 'Vous n\'avez pas accès à cette ordonnance.');
            return $this->redirectToRoute('app_rdv_index');
        }

        if ($this->isCsrfTokenValid('delete'.$ordonnance->getId(), $request->request->get('_token'))) {
            $em->remove($ordonnance);
            $em->flush();
            $this->addFlash('success', 'Ordonnance supprimée');
        }

        return $this->redirectToRoute('app_rdv_index');
    }

    #[Route('/{id}/pdf', name: 'ordonnance_pdf', methods: ['GET'])]
    public function pdf(Ordonnance $ordonnance): Response
    {
        try {
            $client = HttpClient::create();
            $response = $client->request('POST', 'http://localhost:3001/generate-ordonnance', [
                'json' => [
                    'reference' => $ordonnance->getReference(),
                    'date' => $ordonnance->getDateCreation()->format('d/m/Y'),
                    'patient' => $ordonnance->getPatientNom(),
                    'medecin' => $ordonnance->getMedecinNom(),
                    'diagnostic' => $ordonnance->getDiagnostic(),
                    'notes' => $ordonnance->getNotes()
                ]
            ]);

            if ($response->getStatusCode() !== 200) {
                throw new \Exception("PDF API error");
            }

            return new Response(
                $response->getContent(),
                200,
                [
                    'Content-Type' => 'application/pdf',
                    'Content-Disposition' => 'inline; filename="ordonnance.pdf"'
                ]
            );
        } catch (\Exception $e) {
            $this->addFlash('danger', 'Service PDF indisponible');
            return $this->redirectToRoute('ordonnance_show', ['id' => $ordonnance->getId()]);
        }
    }
    
}