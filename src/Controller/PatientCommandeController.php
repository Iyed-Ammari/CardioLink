<?php
// src/Controller/PatientCommandeController.php

namespace App\Controller;

use App\Entity\Commande;
use App\Entity\User;
use App\Repository\CommandeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/patient/commandes')]
#[IsGranted('ROLE_USER')]
final class PatientCommandeController extends AbstractController
{
    private function getLocalUser(EntityManagerInterface $em): User
    {
        $user = $this->getUser();
        if ($user instanceof User) return $user;

        $user = $em->getRepository(User::class)->findOneBy(['email' => 'patient@cardiolink.tn']);
        if (!$user) {
            throw $this->createNotFoundException("User introuvable.");
        }
        return $user;
    }

    #[Route('', name: 'patient_commandes_index', methods: ['GET'])]
    public function index(CommandeRepository $repo, EntityManagerInterface $em): Response
    {
        $user      = $this->getLocalUser($em);
        $commandes = $repo->findCommandesByUser($user);

        return $this->render('patient/commande/index.html.twig', [
            'commandes' => $commandes,
        ]);
    }

    #[Route('/{id}/cancel', name: 'patient_commande_cancel', methods: ['POST'])]
    public function cancel(int $id, CommandeRepository $repo, EntityManagerInterface $em): RedirectResponse
    {
        $user     = $this->getLocalUser($em);
        $commande = $repo->find($id);

        if (!$commande) {
            $this->addFlash('danger', 'Commande introuvable');
            return $this->redirectToRoute('patient_commandes_index');
        }

        if ($commande->getUser()->getId() !== $user->getId()) {
            $this->addFlash('danger', 'Accès interdit');
            return $this->redirectToRoute('patient_commandes_index');
        }

        if (!in_array($commande->getStatut(), [
            Commande::STATUT_PANIER,
            Commande::STATUT_EN_ATTENTE_PAIEMENT
        ], true)) {
            $this->addFlash('danger', 'Annulation interdite pour ce statut');
            return $this->redirectToRoute('patient_commandes_index');
        }

        if ($commande->getStatut() === Commande::STATUT_EN_ATTENTE_PAIEMENT) {
            foreach ($commande->getLignes() as $l) {
                $p = $l->getProduit();
                $p->setStock(($p->getStock() ?? 0) + $l->getQuantite());
            }
        }

        $commande->annuler();
        $em->flush();

        $this->addFlash('success', 'Commande annulée');
        return $this->redirectToRoute('patient_commandes_index');
    }

    
    #[Route('/{id}/facture', name: 'patient_commande_facture', methods: ['GET'])]
    public function facture(int $id, CommandeRepository $repo, EntityManagerInterface $em): Response
    {
        $user     = $this->getLocalUser($em);
        $commande = $repo->find($id);

        if (!$commande) {
            $this->addFlash('danger', 'Commande introuvable');
            return $this->redirectToRoute('patient_commandes_index');
        }

        if ($commande->getUser()->getId() !== $user->getId()) {
            $this->addFlash('danger', 'Accès interdit');
            return $this->redirectToRoute('patient_commandes_index');
        }

        if (!in_array($commande->getStatut(), [
            Commande::STATUT_PAYEE,
            Commande::STATUT_LIVREE
        ], true)) {
            $this->addFlash('danger', 'Facture disponible uniquement pour les commandes payées.');
            return $this->redirectToRoute('patient_commandes_index');
        }

        $html = $this->renderView('patient/commande/facture_pdf.html.twig', [
            'commande'        => $commande,
            'user'            => $user,
            'dateGeneration'  => new \DateTimeImmutable(),
            'numeroFacture'   => 'FACT-' . str_pad((string) $commande->getId(), 6, '0', STR_PAD_LEFT),
        ]);

        $dompdf = new \Dompdf\Dompdf();
        $dompdf->getOptions()->setChroot($this->getParameter('kernel.project_dir') . '/public');
        $dompdf->getOptions()->setIsRemoteEnabled(true);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $filename = 'facture-' . $commande->getId() . '-cardiolink.pdf';

        return new Response(
            $dompdf->output(),
            200,
            [
                'Content-Type'        => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ]
        );
    }
}