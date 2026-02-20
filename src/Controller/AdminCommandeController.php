<?php

namespace App\Controller;

use App\Entity\Commande;
use App\Repository\CommandeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/commandes')]
#[IsGranted('ROLE_ADMIN')]
final class AdminCommandeController extends AbstractController
{
    #[Route('', name: 'admin_commandes_index', methods: ['GET'])]
    public function index(Request $request, CommandeRepository $repo): Response
    {
        $page  = max(1, (int) $request->query->get('page', 1));
        $limit = max(1, min(100, (int) $request->query->get('limit', 50)));

        $pagination = $repo->paginateNonPanier($page, $limit);

        return $this->render('admin/commande/index.html.twig', [
            'commandes' => $pagination['items'],
            'total'     => $pagination['total'],
            'pages'     => $pagination['pages'],
            'page'      => $pagination['page'],
            'limit'     => $pagination['limit'],
        ]);
    }

    #[Route('/{id}/deliver', name: 'admin_commande_deliver', methods: ['POST'])]
    public function deliver(
        int $id,
        Request $request,
        CommandeRepository $repo,
        EntityManagerInterface $em
    ): RedirectResponse {
        if (!$this->isCsrfTokenValid('admin_deliver_'.$id, (string) $request->request->get('_token'))) {
            $this->addFlash('danger', '❌ Token CSRF invalide.');
            return $this->redirectToRoute('admin_commandes_index');
        }

        $commande = $repo->find($id);
        if (!$commande) {
            $this->addFlash('danger', 'Commande introuvable');
            return $this->redirectToRoute('admin_commandes_index');
        }

        if ($commande->getStatut() !== Commande::STATUT_PAYEE) {
            $this->addFlash('danger', 'Transition interdite : LIVREE uniquement depuis PAYEE');
            return $this->redirectToRoute('admin_commandes_index');
        }

        try {
            $commande->marquerLivree();
            $em->flush();
            $this->addFlash('success', '✅ Commande #'.$id.' marquée LIVREE');
        } catch (\Throwable $e) {
            $this->addFlash('danger', '❌ Erreur : '.$e->getMessage());
        }

        $page  = (int) $request->query->get('page', 1);
        $limit = (int) $request->query->get('limit', 50);

        return $this->redirectToRoute('admin_commandes_index', [
            'page'  => max(1, $page),
            'limit' => max(1, min(100, $limit)),
        ]);
    }
}