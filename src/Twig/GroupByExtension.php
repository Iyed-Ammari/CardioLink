<?php

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class GroupByExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('group_by', [$this, 'groupBy']),
        ];
    }

    /**
     * Groupe une collection/tableau par une propriété
     * 
     * @param iterable $items
     * @param string $property
     * @return array
     */
    public function groupBy(iterable $items, string $property): array
    {
        $grouped = [];

        foreach ($items as $item) {
            // Récupérer la valeur de la propriété
            $value = null;
            
            if (is_object($item)) {
                // Essayer d'accéder par getter
                $getter = 'get' . ucfirst($property);
                if (method_exists($item, $getter)) {
                    $value = $item->$getter();
                } else if (property_exists($item, $property)) {
                    // Accès direct à la propriété
                    $value = $item->$property;
                }
            } else if (is_array($item)) {
                // Pour les tableaux
                $value = $item[$property] ?? null;
            }

            if ($value !== null) {
                $key = (string) $value;
                if (!isset($grouped[$key])) {
                    $grouped[$key] = [];
                }
                $grouped[$key][] = $item;
            }
        }

        return $grouped;
    }
}
