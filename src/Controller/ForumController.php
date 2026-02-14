<?php

namespace App\Controller;
use App\Entity\PostSummary;
use App\Entity\Post;
use App\Repository\PostRepository;
use App\Repository\CommentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\String\Slugger\SluggerInterface;
class ForumController extends AbstractController
{
    // Page d'accueil
    #[Route('/forum', name: 'forum_index')]
    public function index(): Response
    {
        return $this->render('forum/index.html.twig');
    }

    // =============================
    // FRONT-OFFICE (affichage + création)
    // =============================
 #[Route('/forum/frontoffice', name: 'forum_frontoffice')]
public function frontoffice(
    Request $request,
    PostRepository $postRepository,
    EntityManagerInterface $em
): Response {
    $user = $this->getUser();

    if (!$user) {
        $this->addFlash('error', 'Vous devez être connecté pour créer un post');
        return $this->redirectToRoute('app_login');
    }

    // ===== CREATION D'UN POST =====
    if ($request->isMethod('POST') && !$request->query->get('search') && !$request->query->get('sort')) {
        $post = new Post();
        $post->setUser($user);
        $post->setTitle($request->request->get('title'));
        $post->setContent($request->request->get('content'));
        $post->setCreatedAT(new \DateTimeImmutable());

        $file = $request->files->get('image');
        if ($file) {
            $filename = uniqid().'.'.$file->guessExtension();
            $file->move($this->getParameter('posts_images_directory'), $filename);
            $post->setImage($filename);
        }

        $em->persist($post);
        $em->flush();

        $this->addFlash('success', 'Post créé avec succès ! 🔥');
        return $this->redirectToRoute('forum_frontoffice');
    }

    // ===== CHARGEMENT DES POSTS =====
    $allPosts = $postRepository->findBy([], ['createdAT' => 'DESC']);

    // ===== RECHERCHE =====
    $search = $request->query->get('search', '');
    if ($search) {
        $allPosts = array_filter($allPosts, fn($post) => stripos($post->getTitle(), $search) !== false);
    }

    // ===== TRI =====
    $sort = $request->query->get('sort', 'recent');
    usort($allPosts, function($a, $b) use ($sort) {
        if ($sort === 'titre-asc') return strcasecmp($a->getTitle(), $b->getTitle());
        if ($sort === 'titre-desc') return strcasecmp($b->getTitle(), $a->getTitle());
        if ($sort === 'ancien') return $a->getCreatedAT() <=> $b->getCreatedAT();
        return $b->getCreatedAT() <=> $a->getCreatedAT(); // recent ou défaut
    });

    // ===== RÉCUPÉRATION DES RÉSUMÉS IA =====
    $postIds = array_map(fn($p) => $p->getId(), $allPosts);
    $postSummaries = $em->getRepository(PostSummary::class)
        ->createQueryBuilder('ps')
        ->where('ps.post IN (:postIds)')
        ->setParameter('postIds', $postIds)
        ->orderBy('ps.createdAt', 'DESC')
        ->getQuery()
        ->getResult();

    $summariesByPost = [];
    foreach ($postSummaries as $ps) {
        $postId = $ps->getPost()->getId();
        if (!isset($summariesByPost[$postId])) {
            $summariesByPost[$postId] = $ps->getSummary();
        }
    }

   

    // ===== CALCUL DES FLAMMES PAR UTILISATEUR =====
    // ===== CALCUL DES FLAMMES PAR UTILISATEUR (TEST 1 MINUTE) =====
// ===== CALCUL DES FLAMMES PAR UTILISATEUR (TEST 1 MINUTE) =====
// ===== CALCUL DES FLAMMES PAR UTILISATEUR (1 MINUTE TEST) =====
// ===== CALCUL DES FLAMMES PAR UTILISATEUR =====
$flamesByUser = [];
$now = new \DateTimeImmutable();
$usersProcessed = [];

foreach ($allPosts as $post) {
    $userId = $post->getUser()->getId();

    if (in_array($userId, $usersProcessed)) {
        continue;
    }

    // Récupérer tous les posts de l'utilisateur, du plus récent au plus ancien
    $userPosts = $postRepository->findBy(['user' => $post->getUser()], ['createdAT' => 'DESC']);

    $flames = 0;
    $previous = null;

    foreach ($userPosts as $userPost) {
        if (!$previous) {
            // Premier post, vérifie si dans la dernière minute
            $diff = $now->getTimestamp() - $userPost->getCreatedAT()->getTimestamp();
            if ($diff <= 60) { // 1 minute
                $flames = 1;
                $previous = $userPost->getCreatedAT();
            } else {
                $flames = 0;
                break;
            }
        } else {
            // Diff entre ce post et le précédent
            $diff = $previous->getTimestamp() - $userPost->getCreatedAT()->getTimestamp();
            if ($diff <= 60) {
                $flames++;
                $previous = $userPost->getCreatedAT();
            } else {
                break;
            }
        }
    }

    $flamesByUser[$userId] = $flames;
    $usersProcessed[] = $userId;
}
    // ===== RENDER =====
    return $this->render('forum/frontoffice.html.twig', [
        'posts' => $allPosts,
        'search' => $search,
        'sort' => $sort,
        'flamesByUser' => $flamesByUser,
        'summariesByPost' => $summariesByPost,
    ]);
}
    // =============================
    // MODIFIER UN POST (FRONT-OFFICE)
    // =============================
    #[Route('/forum/{id}/edit', name: 'forum_edit', methods: ['GET', 'POST'])]
    public function edit(
        Post $post,
        Request $request,
        EntityManagerInterface $em
    ): Response {
        $user = $this->getUser();

        if (!$user) {
            $this->addFlash('error', 'Vous devez être connecté');
            return $this->redirectToRoute('app_login');
        }

        // Vérifier si l'utilisateur est le propriétaire du post
        if ($post->getUser()->getId() !== $user->getId()) {
            $this->addFlash('error', 'Vous n\'avez pas le droit de modifier ce post');
            return $this->redirectToRoute('forum_frontoffice');
        }

        // Si formulaire soumis
        if ($request->isMethod('POST')) {
            $post->setTitle($request->request->get('title'));
            $post->setContent($request->request->get('content'));
            $file = $request->files->get('image');
            if ($file) {
                $filename = uniqid().'.'.$file->guessExtension();
                $file->move($this->getParameter('posts_images_directory'), $filename);
                $post->setImage($filename);
            }
            $em->flush();

            $this->addFlash('success', 'Post modifié avec succès');
            return $this->redirectToRoute('forum_frontoffice');
        }

        // Sinon afficher le formulaire pré-rempli
        return $this->render('forum/frontoffice.html.twig', [
            'post' => $post,
        ]);
    }

    // =============================
    // BACK-OFFICE (posts + commentaires)
    // =============================
    #[Route('/forum/backoffice', name: 'forum_backoffice')]
    public function backoffice(
        PostRepository $postRepository,
        CommentRepository $commentRepository
    ): Response {
        $user = $this->getUser();

        if (!$user || !in_array('ROLE_ADMIN', $user->getRoles())) {
            $this->addFlash('error', 'Vous devez être administrateur pour accéder au back-office');
            return $this->redirectToRoute('forum_frontoffice');
        }

        return $this->render('forum/backoffice.html.twig', [
            'posts' => $postRepository->findAll(),
            'comments' => $commentRepository->findAll(),
        ]);
    }

    // =============================
    // SUPPRIMER POST
    // =============================
    #[Route('/forum/{id}/delete', name: 'forum_delete', methods: ['POST'])]
    public function delete(Post $post, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();

        if (!$user) {
            $this->addFlash('error', 'Vous devez être connecté');
            return $this->redirectToRoute('app_login');
        }

        // Vérifier si l'utilisateur est propriétaire du post ou admin
        if ($post->getUser()->getId() !== $user->getId() && !in_array('ROLE_ADMIN', $user->getRoles())) {
            $this->addFlash('error', 'Vous n\'avez pas le droit de supprimer ce post');
            return $this->redirectToRoute('forum_frontoffice');
        }

        // Supprimer d'abord tous les commentaires liés
        foreach ($post->getComments() as $comment) {
            $em->remove($comment);
        }

        // Puis supprimer le post
        $em->remove($post);
        $em->flush();

        $this->addFlash('success', 'Post supprimé avec succès');
        
        // Redirection selon le rôle
        if (in_array('ROLE_ADMIN', $user->getRoles())) {
            return $this->redirectToRoute('forum_backoffice');
        }
        return $this->redirectToRoute('forum_frontoffice');
    }

    // =============================
    // VOIR UN POST
    // =============================
    #[Route('/forum/{id}', name: 'forum_show')]
    public function show(Post $post): Response
    {
        return $this->render('forum/show.html.twig', [
            'post' => $post,
        ]);
    }
#[Route('/post/{id}/like', name: 'post_like')]
public function like(Post $post, EntityManagerInterface $em): Response
{
    $user = $this->getUser();

    if (!$post->isLikedByUser($user)) {
        $post->addLikedBy($user);
    }

    $em->flush();

    return $this->redirectToRoute('forum_frontoffice');
}
#[Route('/post/{id}/like', name: 'post_like', methods: ['POST'])]
public function toggleLike(Post $post, EntityManagerInterface $em): JsonResponse
{
    $user = $this->getUser();
    if (!$user) {
        return $this->json(['error' => 'Not authenticated'], 403);
    }

    if ($post->isLikedByUser($user)) {
        $post->removeLikedBy($user); // retire le like
        $liked = false;
    } else {
        $post->addLikedBy($user); // ajoute le like
        $liked = true;
    }

    $em->persist($post);
    $em->flush();

    return $this->json([
        'likes' => $post->getLikes(),
        'liked' => $liked
    ]);
}
public function forumFrontoffice(Request $request, EntityManagerInterface $em)
{
    if ($request->isMethod('POST')) {

        dd($request->files->all()); // 🔥 test uniquement quand tu publies

        $post = new Post();
        $post->setContent($request->request->get('content'));
        $post->setTitle($request->request->get('title'));
        $post->setCreatedAT(new \DateTimeImmutable());
        $post->setUser($this->getUser());

        $file = $request->files->get('image');

        if ($file) {
            $filename = uniqid().'.'.$file->guessExtension();

            $file->move(
                $this->getParameter('posts_images_directory'),
                $filename
            );

            $post->setImage($filename);
        }

        $em->persist($post);
        $em->flush();

        return $this->redirectToRoute('forum_frontoffice');
    }

    return $this->render('forum/frontoffice.html.twig', [
        'posts' => $em->getRepository(Post::class)->findAll()
    ]);
}
#[Route('/forum/post/{id}/summary', name: 'generate_summary', methods: ['POST'])]
public function generateSummary(Post $post, EntityManagerInterface $em): Response
{
    $content = $post->getContent();

    // Appel à l'API Flask
    $url = "http://127.0.0.1:5000/summarize";
    $data = json_encode(["text" => $content]);

    $options = [
        "http" => [
            "header"  => "Content-Type: application/json",
            "method"  => "POST",
            "content" => $data,
            "ignore_errors" => true
        ]
    ];

    $context  = stream_context_create($options);
    $result = file_get_contents($url, false, $context);
    $response = json_decode($result, true);

    $summary = $response['summary'] ?? "Résumé non disponible";

    // 🔥 Vérifier si un PostSummary existe déjà pour ce Post
    $postSummary = $em->getRepository(PostSummary::class)->findOneBy(['post' => $post]);

    if (!$postSummary) {
        // Si aucun résumé → création
        $postSummary = new PostSummary();
        $postSummary->setPost($post);
        $postSummary->setCreatedAt(new \DateTimeImmutable());
    }

    // Toujours mettre à jour le résumé
    $postSummary->setSummary($summary);

    $em->persist($postSummary);
    $em->flush();

    $this->addFlash('success', 'Résumé généré avec succès !');

    return $this->redirectToRoute('forum_frontoffice');
}
}