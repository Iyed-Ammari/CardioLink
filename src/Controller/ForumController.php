<?php

namespace App\Controller;

use App\Entity\PostSummary;
use App\Entity\Post;
use App\Repository\PostRepository;
use App\Repository\CommentRepository;
use App\Repository\PostSummaryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
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
    EntityManagerInterface $em,
    HttpClientInterface $client
): Response {
    $user = $this->getUser();
    if (!$user) {
        $this->addFlash('error', 'Vous devez être connecté pour créer un post');
        return $this->redirectToRoute('app_login');
    }

    // 1. GESTION DE LA CRÉATION
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

        try {
            $response = $client->request('POST', 'http://127.0.0.1:8002/embed', [
                'json' => ['text' => $post->getContent()],
                'timeout' => 2
            ]);
            if ($response->getStatusCode() === 200) {
                $post->setEmbedding($response->toArray()['vector']);
            }
        } catch (\Exception $e) {
            // IA indisponible, on continue sans erreur
        }

        $em->persist($post);
        $em->flush();
        $this->addFlash('success', 'Post créé avec succès !');
        return $this->redirectToRoute('forum_frontoffice');
    }

    // 2. CHARGEMENT INITIAL
    $allPosts = $postRepository->findBy([], ['createdAT' => 'DESC']);

    // 3. RECHERCHE (Une seule fois suffit)
    $search = $request->query->get('search', '');
    if ($search) {
        $allPosts = array_filter($allPosts, fn($p) => stripos($p->getTitle(), $search) !== false);
    }

    // 4. 🤖 IA MATCHING (Suggestions basées sur le post le plus récent de la liste)
    // 3. 🤖 IA MATCHING PERSONNALISÉ
$recommendedPosts = [];

// On cherche le tout dernier post écrit par l'utilisateur connecté
$lastUserPost = $postRepository->findOneBy(
    ['user' => $user], 
    ['createdAT' => 'DESC']
);

if ($lastUserPost && $lastUserPost->getEmbedding()) {
    // L'IA compare le dernier post de l'utilisateur avec TOUT le reste du forum
    $recommendedPosts = $postRepository->findSimilarPosts(
        $lastUserPost->getEmbedding(), 
        $lastUserPost->getId() // On exclut son propre post des résultats
    );
}
    // 5. TRI
    $sort = $request->query->get('sort', 'recent');
    usort($allPosts, function($a, $b) use ($sort) {
        if ($sort === 'titre-asc') return strcasecmp($a->getTitle(), $b->getTitle());
        if ($sort === 'titre-desc') return strcasecmp($b->getTitle(), $a->getTitle());
        if ($sort === 'ancien') return $a->getCreatedAT() <=> $b->getCreatedAT();
        return $b->getCreatedAT() <=> $a->getCreatedAT();
    });

    // 6. RÉSUMÉS
    $postIds = array_map(fn($p) => $p->getId(), $allPosts);
    $summariesByPost = [];
    if (!empty($postIds)) {
        $postSummaries = $em->getRepository(PostSummary::class)
            ->createQueryBuilder('ps')
            ->where('ps.post IN (:postIds)')
            ->setParameter('postIds', $postIds)
            ->getQuery()
            ->getResult();

        foreach ($postSummaries as $ps) {
            $summariesByPost[$ps->getPost()->getId()] = $ps->getSummary();
        }
    }

    // 7. CALCUL DES FLAMMES (Optimisé)
    $flamesByUser = [];
    $now = new \DateTimeImmutable();
    $usersProcessed = [];

    foreach ($allPosts as $post) {
        $postUser = $post->getUser();
        $userId = $postUser->getId();

        if (in_array($userId, $usersProcessed)) continue;

        $userPosts = $postRepository->findBy(['user' => $postUser], ['createdAT' => 'DESC']);
        $flames = 0;
        $previous = null;

        foreach ($userPosts as $userPost) {
            if (!$previous) {
                $diff = $now->getTimestamp() - $userPost->getCreatedAT()->getTimestamp();
                if ($diff <= 60) { 
                    $flames = 1;
                    $previous = $userPost->getCreatedAT();
                } else { break; }
            } else {
                $diff = $previous->getTimestamp() - $userPost->getCreatedAT()->getTimestamp();
                if ($diff <= 60) {
                    $flames++;
                    $previous = $userPost->getCreatedAT();
                } else { break; }
            }
        }
        $flamesByUser[$userId] = $flames;
        $usersProcessed[] = $userId;
    }
    dump($recommendedPosts);
    return $this->render('forum/frontoffice.html.twig', [
        'posts' => $allPosts,
        'search' => $search,
        'sort' => $sort,
        'recommendedPosts' => $recommendedPosts,
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
                $filename = uniqid() . '.' . $file->guessExtension();
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
    #[Route('/forum/post/{id}', name: 'forum_post_show')]
public function show(Post $post): Response
{
    // On récupère les commentaires (assure-toi d'avoir une relation dans ton entité Post)
    $comments = $post->getComments(); 

    return $this->render('forum/show.html.twig', [
        'post' => $post,
        'comments' => $comments,
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
                $filename = uniqid() . '.' . $file->guessExtension();

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

#[Route('/forum/post/{id}/generate-summary', name: 'generate_summary', methods: ['POST'])]
public function generateSummary(Post $post, EntityManagerInterface $em, PostSummaryRepository $summaryRepo): Response
{
    $content = $post->getContent();

    // 🔹 Chemin du script Python
    $pythonBinary = '"C:\\Users\\Asus\\Desktop\\CardioLink\\ai_service\\venv\\Scripts\\python.exe"';
    $pythonScript = '"C:\\Users\\Asus\\Desktop\\CardioLink\\ml\\summarizer.py"';

        $descriptors = [
            0 => ['pipe', 'r'], // stdin
            1 => ['pipe', 'w'], // stdout
            2 => ['pipe', 'w'], // stderr
        ];

        $process = proc_open("$pythonBinary $pythonScript", $descriptors, $pipes);

        if (is_resource($process)) {
            fwrite($pipes[0], $content);
            fclose($pipes[0]);

            $summary = stream_get_contents($pipes[1]);
            fclose($pipes[1]);

            $errorOutput = stream_get_contents($pipes[2]);
            fclose($pipes[2]);

            proc_close($process);

            if (!empty($errorOutput)) {
                $summary = "Erreur Python : " . $errorOutput;
            }

            $summary = trim($summary);
        } else {
            $summary = "Résumé non disponible";
        }
        // 🔹 Stocker ou mettre à jour le résumé dans PostSummary
        $existingSummary = $summaryRepo->findOneBy(['post' => $post]);
        if ($existingSummary) {
            $existingSummary->setSummary($summary);
            $existingSummary->setCreatedAt(new \DateTimeImmutable());
        } else {
            $postSummary = new PostSummary();
            $postSummary->setPost($post);
            $postSummary->setSummary($summary);
            $postSummary->setCreatedAt(new \DateTimeImmutable());
            $em->persist($postSummary);
        }

        $em->flush();

        $this->addFlash('success', 'Résumé généré avec succès !');

    return $this->redirectToRoute('forum_frontoffice');
}

}
