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
use Symfony\Contracts\HttpClient\HttpClientInterface;
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
#[Route('/post/{postId}/comment/add', name: 'comment_add', methods: ['POST'])]
public function add(Request $request, int $postId, HttpClientInterface $httpClient): Response
{
    $user = $this->getUser();
    if (!$user) {
        $this->addFlash('error', 'Vous devez être connecté');
        return $this->redirectToRoute('app_login');
    }

    $post = $this->postRepo->find($postId);
    $content = trim($request->request->get('content', ''));

    if (!$post || empty($content)) {
        $this->addFlash('error', 'Le commentaire ne peut pas être vide.');
        return $this->redirectToRoute('forum_frontoffice');
    }

    // --- BLOC IA ---
    try {
        $response = $httpClient->request('POST', 'http://127.0.0.1:8001/predict', [
            'json' => ['text' => $content],
            'timeout' => 2,
        ]);

        $data = $response->toArray();
        
        // On récupère le label (ex: "negative", "positive", "neutral")
        $label = isset($data['label']) ? strtolower($data['label']) : 'positive';

        // LOGIQUE : Si c'est négatif, on arrête TOUT et on redirige
        if ($label === 'negative') {
            $this->addFlash('error', '⚠️ Votre commentaire contient des propos inappropriés et a été refusé.');
            return $this->redirectToRoute('forum_frontoffice');
        }

    } catch (\Exception $e) {
        // Si Python est éteint, on laisse passer pour ne pas casser le site
    }

    // --- ENREGISTREMENT ---
    // Cette partie n'est lue QUE SI le label n'était pas "negative"
    $comment = new Comment();
    $comment->setPost($post);
    $comment->setUser($user);
    $comment->setContent($content);
    $comment->setCreatedAt(new \DateTimeImmutable());

    $this->em->persist($comment);
    $this->em->flush();

    $this->addFlash('success', 'Commentaire ajouté avec succès !');
    return $this->redirectToRoute('forum_frontoffice');
}
    // =============================
    // Modifier un commentaire
    // =============================
   #[Route('/comment/{id}/edit', name: 'comment_edit', methods: ['POST'])]
public function edit(Request $request, int $id, HttpClientInterface $httpClient): Response
{
    // 1. Sécurité : Vérification de l'utilisateur connecté
    $user = $this->getUser();
    if (!$user) {
        $this->addFlash('error', 'Vous devez être connecté');
        return $this->redirectToRoute('app_login');
    }

    // 2. Récupération du commentaire
    $comment = $this->commentRepo->find($id);
    if (!$comment) {
        $this->addFlash('error', 'Commentaire introuvable');
        return $this->redirectToRoute('forum_frontoffice');
    }

    // 3. Vérification du propriétaire du commentaire
    if ($comment->getUser()->getId() !== $user->getId()) {
        $this->addFlash('error', 'Vous ne pouvez pas modifier ce commentaire');
        return $this->redirectToRoute('forum_frontoffice');
    }

    // 4. Récupération du nouveau contenu
    $content = trim($request->request->get('content', ''));
    if (empty($content)) {
        $this->addFlash('error', 'Le commentaire ne peut pas être vide');
        return $this->redirectToRoute('forum_frontoffice');
    }

    // 5. APPEL À L'IA (Modération du nouveau texte)
    try {
            $response = $httpClient->request('POST', 'http://127.0.0.1:8001/predict', [
                'json' => ['text' => $content],
                'timeout' => 2,
            ]);

            $data = $response->toArray();
            $label = isset($data['label']) ? strtolower($data['label']) : 'positive';

            // Si l'IA détecte du négatif, on BLOQUE et on REDIRIGE vers le forum
            if ($label === 'negative') {
                $this->addFlash('error', '⚠️ Modification refusée : le contenu est inapproprié.');
                return $this->redirectToRoute('forum_frontoffice');
            }
        } catch (\Exception $e) {
            // Si le serveur Python est éteint, on ignore l'erreur et on laisse modifier
        }

    // 6. Mise à jour si tout est OK
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