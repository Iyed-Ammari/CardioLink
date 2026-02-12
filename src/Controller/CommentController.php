<?php
namespace App\Controller;

use App\Entity\Comment;
use App\Repository\CommentRepository;
use App\Repository\PostRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class CommentController extends AbstractController
{
    private $em;
    private $commentRepo;
    private $postRepo;
    private $userRepo;

    public function __construct(EntityManagerInterface $em, CommentRepository $commentRepo, PostRepository $postRepo, UserRepository $userRepo)
    {
        $this->em = $em;
        $this->commentRepo = $commentRepo;
        $this->postRepo = $postRepo;
        $this->userRepo = $userRepo;
    }

    // =============================
    // Ajouter un commentaire
    // =============================
    #[Route('/post/{postId}/comment/add', name: 'comment_add', methods:['POST'])]
    public function add(Request $request, int $postId): Response
    {
        $user = $this->getUser();
        if (!$user) {
            $this->addFlash('error', 'Vous devez être connecté');
            return $this->redirectToRoute('app_login');
        }

        $post = $this->postRepo->find($postId);
        if (!$post) {
            $this->addFlash('error', 'Post introuvable');
            return $this->redirectToRoute('forum_frontoffice');
        }

        $content = $request->request->get('content');

        if (!$content) {
            $this->addFlash('error', 'Le commentaire ne peut pas être vide');
            return $this->redirectToRoute('forum_frontoffice');
        }

        $comment = new Comment();
        $comment->setPost($post);
        $comment->setUser($user);
        $comment->setContent($content);
        $comment->setCreatedAt(new \DateTimeImmutable());

        $this->em->persist($comment);
        $this->em->flush();

        $this->addFlash('success', 'Commentaire ajouté avec succès');
        return $this->redirectToRoute('forum_frontoffice');
    }

    // =============================
    // Modifier un commentaire
    // =============================
    #[Route('/comment/{id}/edit', name: 'comment_edit', methods:['POST'])]
    public function edit(Request $request, int $id): Response
    {
        $user = $this->getUser();
        if (!$user) {
            $this->addFlash('error', 'Vous devez être connecté');
            return $this->redirectToRoute('app_login');
        }

        $comment = $this->commentRepo->find($id);
        if (!$comment) {
            $this->addFlash('error', 'Commentaire introuvable');
            return $this->redirectToRoute('forum_frontoffice');
        }

        if ($comment->getUser()->getId() !== $user->getId()) {
            $this->addFlash('error', 'Vous ne pouvez pas modifier ce commentaire');
            return $this->redirectToRoute('forum_frontoffice');
        }

        $content = $request->request->get('content');
        if (!$content) {
            $this->addFlash('error', 'Le commentaire ne peut pas être vide');
            return $this->redirectToRoute('forum_frontoffice');
        }

        $comment->setContent($content);
        $this->em->flush();

        $this->addFlash('success', 'Commentaire modifié avec succès');
        return $this->redirectToRoute('forum_frontoffice');
    }

    // =============================
    // Supprimer un commentaire
    // =============================
    #[Route('/comment/{id}/delete', name: 'comment_delete', methods:['POST'])]
    public function delete(int $id): Response
    {
        $user = $this->getUser();
        if (!$user) {
            $this->addFlash('error', 'Vous devez être connecté');
            return $this->redirectToRoute('app_login');
        }

        $comment = $this->commentRepo->find($id);
        if (!$comment) {
            $this->addFlash('error', 'Commentaire introuvable');
            return $this->redirectToRoute('forum_frontoffice');
        }

        // Si admin, peut supprimer n'importe quel commentaire
        if (in_array('ROLE_ADMIN', $user->getRoles())) {
            $this->em->remove($comment);
            $this->em->flush();
            $this->addFlash('success', 'Commentaire supprimé avec succès');
            return $this->redirectToRoute('forum_backoffice');
        }

        // Sinon, peut supprimer uniquement son propre commentaire
        if ($comment->getUser()->getId() !== $user->getId()) {
            $this->addFlash('error', 'Vous ne pouvez pas supprimer ce commentaire');
            return $this->redirectToRoute('forum_frontoffice');
        }

        $this->em->remove($comment);
        $this->em->flush();

        $this->addFlash('success', 'Commentaire supprimé avec succès');
        return $this->redirectToRoute('forum_frontoffice');
    }
}