<?php

namespace App\Repository;

use App\Entity\Post;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Post>
 */
class PostRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Post::class);
    }

    /**
     * Trouve les posts les plus proches sémantiquement
     */
    public function findSimilarPosts(array $targetVector, int $currentPostId, int $limit = 3): array
{
    // 1. On récupère tous les posts qui ont un embedding (sauf l'actuel)
    $allPosts = $this->createQueryBuilder('p')
        ->where('p.embedding IS NOT NULL')
        ->andWhere('p.id != :currentId')
        ->setParameter('currentId', $currentPostId)
        ->getQuery()
        ->getResult();

    $matches = [];

    foreach ($allPosts as $post) {
        $vectorFromDb = $post->getEmbedding();

        // Sécurité : Si la DB renvoie une chaîne au lieu d'un tableau, on la décode
        if (is_string($vectorFromDb)) {
            $vectorFromDb = json_decode($vectorFromDb, true);
        }

        if (is_array($vectorFromDb) && !empty($vectorFromDb)) {
            $score = $this->cosineSimilarity($targetVector, $vectorFromDb);
            
            // On baisse le seuil à 0.10 pour être sûr de voir des résultats au début
            if ($score > 0.10) {
                $matches[] = [
                    'post' => $post,
                    'score' => round($score * 100, 1) // Score en % (ex: 85.5)
                ];
            }
        }
    }

    // Trier du plus pertinent au moins pertinent
    usort($matches, fn($a, $b) => $b['score'] <=> $a['score']);

    return array_slice($matches, 0, $limit);
}
    /**
     * Formule mathématique de la Similarité Cosinus
     * Elle compare l'angle entre deux vecteurs dans l'espace
     */
    private function cosineSimilarity(array $vec1, array $vec2): float
    {
        $dotProduct = 0;
        $normA = 0;
        $normB = 0;

        foreach ($vec1 as $i => $val) {
            // Sécurité au cas où les vecteurs n'auraient pas la même taille
            if (!isset($vec2[$i])) continue; 

            $dotProduct += $val * $vec2[$i];
            $normA += $val ** 2;
            $normB += $vec2[$i] ** 2;
        }

        if ($normA == 0 || $normB == 0) {
            return 0;
        }

        return $dotProduct / (sqrt($normA) * sqrt($normB));
    }
}