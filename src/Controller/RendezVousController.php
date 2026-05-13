<?php

namespace App\Controller;

use App\Entity\Lieu;
use App\Entity\RendezVous;
use App\Entity\User;
use App\Form\RendezVousType;
use App\Repository\LieuRepository;
use App\Repository\RendezVousRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Process\Process;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[Route('/rdv')]
class RendezVousController extends AbstractController
{
    /**
     * Liste des rendez-vous avec recherche et tri réel
     */
    #[Route('/', name: 'app_rdv_index', methods: ['GET'])]
    public function index(Request $request, RendezVousRepository $rendezVousRepository): Response
    {
        $user = $this->getUser();

        // Récupération des paramètres de recherche et de tri depuis l'URL
        $search = $request->query->get('search');
        $sort = $request->query->get('sort');   // 'patient', 'medecin' ou 'dateHeure'
        $order = $request->query->get('order'); // 'ASC' ou 'DESC'


        // Dans RendezVousController.php, modifie l'appel :
        // RendezVousController.php ligne 34
        $role = in_array('ROLE_MEDECIN', $user->getRoles()) ? 'ROLE_MEDECIN' : 'ROLE_PATIENT';

        $rdvs = $rendezVousRepository->searchGlobal(
            $search,
            $sort,
            $order,
            $user,
            $role
        );

        return $this->render('rendez_vous/index.html.twig', [
            'rendez_vous' => $rdvs,
        ]);
    }

    /**
     * Création d'un nouveau rendez-vous (Réservé aux Patients)
     */
    #[Route('/nouveau', name: 'app_rdv_new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_PATIENT')]
    public function new(Request $request, EntityManagerInterface $entityManager, RendezVousRepository $repo, LieuRepository $lieuRepository): Response
    {
        $rdv = new RendezVous();
        $rdv->setPatient($this->getUser());
        $rdv->setStatut('En attente');

        $form = $this->createForm(RendezVousType::class, $rdv);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            // 1. Vérification anti-collision
            $conflits = $repo->countCrenau($rdv->getDateHeure(), $rdv->getMedecin());
            if ($conflits > 0) {
                $this->addFlash('danger', 'Le médecin n\'est pas disponible à ce créneau.');
                return $this->render('rendez_vous/new.html.twig', [
                    'form' => $form,
                    'rendez_vous' => $rdv
                ]);
            }

            // 2. Gestion du Type (Visio vs Présentiel)
            if ($rdv->getType() === 'Télémédecine') {
                $uniqueId = uniqid('cardiolink-');
                $rdv->setLienVisio("https://meet.jit.si/$uniqueId");
                $rdv->setLieu(null);
            } else {
                // Assignation auto du lieu selon le cabinet du médecin
                $medecin = $rdv->getMedecin();
                if ($medecin && $medecin->getCabinet()) {
                    $lieu = $lieuRepository->findOneBy(['nom' => $medecin->getCabinet()]);
                    if (!$lieu) {
                        $lieu = new Lieu();
                        $lieu->setNom($medecin->getCabinet());
                        $lieu->setAdresse($medecin->getCabinet());
                        $lieu->setVille($medecin->getAdresse() ?? 'Non renseignée');
                        $lieu->setContact($medecin->getTel());
                        $lieu->setEstVirtuel(false);
                        $entityManager->persist($lieu);
                    }
                    $rdv->setLieu($lieu);
                }
            }

            $entityManager->persist($rdv);
            $entityManager->flush();

            $this->addFlash('success', 'Demande de rendez-vous envoyée !');
            return $this->redirectToRoute('app_rdv_index');
        }

        return $this->render('rendez_vous/new.html.twig', [
            'form' => $form,
            'rendez_vous' => $rdv
        ]);
    }

    /**
     * Modification d'un rendez-vous
     */
    #[Route('/{id}/edit', name: 'app_rdv_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, RendezVous $rendezVous, EntityManagerInterface $entityManager, RendezVousRepository $repo): Response
    {
        if ($rendezVous->getPatient() !== $this->getUser() && $rendezVous->getMedecin() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Accès refusé.');
        }

        $form = $this->createForm(RendezVousType::class, $rendezVous);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            // Vérification anti-collision (en excluant le RDV actuel)
            $conflits = $repo->countCrenau($rendezVous->getDateHeure(), $rendezVous->getMedecin(), $rendezVous->getId());
            if ($conflits > 0) {
                $this->addFlash('danger', 'Créneau indisponible.');
                return $this->render('rendez_vous/edit.html.twig', ['form' => $form, 'rendez_vous' => $rendezVous]);
            }

            // Mise à jour logique visio
            if ($rendezVous->getType() === 'Télémédecine' && empty($rendezVous->getLienVisio())) {
                $rendezVous->setLienVisio("https://meet.jit.si/" . uniqid('cardiolink-'));
                $rendezVous->setLieu(null);
            } elseif ($rendezVous->getType() === 'Présentiel') {
                $rendezVous->setLienVisio(null);
            }

            $entityManager->flush();
            $this->addFlash('success', 'Rendez-vous modifié.');
            return $this->redirectToRoute('app_rdv_index');
        }

        return $this->render('rendez_vous/edit.html.twig', [
            'form' => $form,
            'rendez_vous' => $rendezVous
        ]);
    }

    /**
     * Mise à jour rapide du statut par le médecin
     */
    #[Route('/{id}/update-status', name: 'app_rdv_update_status', methods: ['POST'])]
    public function updateStatus(Request $request, RendezVous $rendezVous, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isGranted('ROLE_MEDECIN')) {
            throw $this->createAccessDeniedException();
        }

        if ($this->isCsrfTokenValid('update_status' . $rendezVous->getId(), $request->request->get('_token'))) {
            $rendezVous->setStatut($request->request->get('statut'));
            $entityManager->flush();
            $this->addFlash('success', 'Statut mis à jour.');
        }

        return $this->redirectToRoute('app_rdv_index');
    }

    /**
     * Suppression d'un rendez-vous
     */
    #[Route('/{id}', name: 'app_rdv_delete', methods: ['POST'])]
    public function delete(Request $request, RendezVous $rendezVous, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete' . $rendezVous->getId(), $request->request->get('_token'))) {
            $entityManager->remove($rendezVous);
            $entityManager->flush();
            $this->addFlash('success', 'Rendez-vous supprimé.');
        }

        return $this->redirectToRoute('app_rdv_index');
    }


    #[Route('/prediction', name: 'app_rdv_prediction', methods: ['GET'])]
    #[IsGranted('ROLE_MEDECIN')]
    public function prediction(HttpClientInterface $client): Response
    {
        $medecin = $this->getUser();
        if ($medecin instanceof User){
            $medecinId = $medecin->getId();
        } else {
            $this->addFlash('danger', 'Utilisateur non reconnu.');
            return $this->redirectToRoute('app_rdv_index');
        }

        try {
            // Symfony appelle l'API Flask
            $response = $client->request('GET', 'http://127.0.0.1:5000/predict/' . $medecinId);
            $data = $response->toArray();
        } catch (\Exception $e) {
            $this->addFlash('danger', 'Le service de prédiction est hors ligne.');
            return $this->redirectToRoute('app_rdv_index');
        }

        return $this->render('rendez_vous/prediction.html.twig', [
            'prediction' => $data['prediction'] ?? 'N/A',
            'historique' => $data['historique'] ?? []
        ]);
    }
}
