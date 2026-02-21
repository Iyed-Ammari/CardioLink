<?php

namespace App\Controller;

use App\Entity\DossierMedical;
use App\Repository\DossierMedicalRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Dompdf\Dompdf;
use Dompdf\Options;

class DossierMedicalController extends AbstractController
{
    // ========== PATIENT ==========

    #[Route('/mon-dossier', name: 'app_mon_dossier')]
    public function monDossier(EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        $dossier = $em->getRepository(DossierMedical::class)->findOneBy(['user' => $user]);

        return $this->render('dossier_medical/mon_dossier.html.twig', [
            'dossier' => $dossier
        ]);
    }

    #[Route('/mon-dossier/edit', name: 'app_mon_dossier_edit')]
    public function editMonDossier(Request $request, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        $dossier = $em->getRepository(DossierMedical::class)->findOneBy(['user' => $user]);

        if (!$dossier) {
            $dossier = new DossierMedical();
            $dossier->setUser($user);
            $em->persist($dossier);
        }

        if ($request->isMethod('POST')) {
            $dossier->edit(
                $request->request->get('groupeSanguin'),
                $request->request->get('antecedents'),
                $request->request->get('allergies'),
                $request->request->get('poids') ? (float)$request->request->get('poids') : null,
                $request->request->get('taille') ? (float)$request->request->get('taille') : null,
                $request->request->get('tensionSystolique') ? (int)$request->request->get('tensionSystolique') : null,
                $request->request->get('tensionDiastolique') ? (int)$request->request->get('tensionDiastolique') : null,
                $request->request->get('frequenceCardiaque') ? (int)$request->request->get('frequenceCardiaque') : null
            );

            $em->flush();
            $this->addFlash('success', 'Dossier mis à jour avec succès.');
            return $this->redirectToRoute('app_mon_dossier');
        }

        return $this->render('dossier_medical/edit.html.twig', [
            'dossier' => $dossier
        ]);
    }

    #[Route('/mon-dossier/pdf', name: 'app_mon_dossier_pdf')]
    public function pdf(EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        $dossier = $em->getRepository(DossierMedical::class)->findOneBy(['user' => $user]);

        $options = new Options();
        $options->set('defaultFont', 'Arial');
        $dompdf = new Dompdf($options);

        $html = '
        <html>
        <body style="font-family: Arial; padding: 30px;">
            <h1 style="color:#F82239;">📋 Dossier Médical - CardioLink</h1>
            <hr>
            <h2>'.$user->getNom().' '.$user->getPrenom().'</h2>
            <p><strong>Email :</strong> '.$user->getEmail().'</p>
            <p><strong>Téléphone :</strong> '.$user->getTel().'</p>
            <hr>
            <h3>Informations Médicales</h3>
            <p><strong>Groupe Sanguin :</strong> '.$dossier->getGroupeSanguin().'</p>
            <p><strong>Antécédents :</strong> '.($dossier->getAntecedents() ?? '-').'</p>
            <p><strong>Allergies :</strong> '.($dossier->getAllergies() ?? '-').'</p>
            <p><strong>Poids :</strong> '.($dossier->getPoids() ? $dossier->getPoids().' kg' : '-').'</p>
            <p><strong>Taille :</strong> '.($dossier->getTaille() ? $dossier->getTaille().' cm' : '-').'</p>
            <p><strong>IMC :</strong> '.($dossier->getIMC() ?? '-').'</p>
            <p><strong>Tension :</strong> '.($dossier->getTensionSystolique() ? $dossier->getTensionSystolique().'/'.$dossier->getTensionDiastolique().' mmHg' : '-').'</p>
            <p><strong>Fréquence Cardiaque :</strong> '.($dossier->getFrequenceCardiaque() ? $dossier->getFrequenceCardiaque().' bpm' : '-').'</p>
            <hr>
            <h3 style="color:'.($dossier->getRisqueCardiaque() === 'CRITIQUE' ? 'red' : ($dossier->getRisqueCardiaque() === 'ÉLEVÉ' ? 'orange' : 'green')).';">
                Risque Cardiaque : '.$dossier->getRisqueCardiaque().'
            </h3>
            <hr>
            <p style="color:gray; font-size:12px;">Généré le '.date('d/m/Y H:i').' - CardioLink</p>
        </body>
        </html>';

        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return new Response(
            $dompdf->output(),
            200,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="dossier_medical.pdf"'
            ]
        );
    }

    // ========== ADMIN ==========

    #[Route('/admin/dossiers', name: 'admin_dossier_index')]
    public function adminIndex(DossierMedicalRepository $repo, Request $request): Response
    {
        $groupeSanguin = $request->query->get('groupeSanguin');

        $qb = $repo->createQueryBuilder('d');

        if ($groupeSanguin) {
            $qb->andWhere('d.groupeSanguin = :gs')
               ->setParameter('gs', $groupeSanguin);
        }

        $dossiers = $qb->getQuery()->getResult();

        return $this->render('dossier_medical/admin_index.html.twig', [
            'dossiers' => $dossiers,
            'groupeSanguin' => $groupeSanguin
        ]);
    }

    #[Route('/admin/dossiers/{id}', name: 'admin_dossier_show')]
    public function adminShow(DossierMedical $dossier): Response
    {
        return $this->render('dossier_medical/admin_show.html.twig', [
            'dossier' => $dossier
        ]);
    }

    #[Route('/admin/dossiers/{id}/edit', name: 'admin_dossier_edit')]
    public function adminEdit(DossierMedical $dossier, Request $request, EntityManagerInterface $em): Response
    {
        if ($request->isMethod('POST')) {
            $dossier->edit(
                $request->request->get('groupeSanguin'),
                $request->request->get('antecedents'),
                $request->request->get('allergies'),
                $request->request->get('poids') ? (float)$request->request->get('poids') : null,
                $request->request->get('taille') ? (float)$request->request->get('taille') : null,
                $request->request->get('tensionSystolique') ? (int)$request->request->get('tensionSystolique') : null,
                $request->request->get('tensionDiastolique') ? (int)$request->request->get('tensionDiastolique') : null,
                $request->request->get('frequenceCardiaque') ? (int)$request->request->get('frequenceCardiaque') : null
            );

            $em->flush();
            $this->addFlash('success', 'Dossier modifié avec succès.');
            return $this->redirectToRoute('admin_dossier_index');
        }

        return $this->render('dossier_medical/admin_edit.html.twig', [
            'dossier' => $dossier
        ]);
    }

    #[Route('/admin/dossiers/{id}/delete', name: 'admin_dossier_delete', methods: ['POST'])]
    public function adminDelete(DossierMedical $dossier, EntityManagerInterface $em, Request $request): Response
    {
        if ($this->isCsrfTokenValid('delete'.$dossier->getId(), $request->request->get('_token'))) {
            $em->remove($dossier);
            $em->flush();
            $this->addFlash('success', 'Dossier supprimé.');
        }

        return $this->redirectToRoute('admin_dossier_index');
    }

    // ========== API ==========

    #[Route('/api/dossiers', name: 'api_dossier_list', methods: ['GET'])]
    public function apiList(DossierMedicalRepository $repo): JsonResponse
    {
        $dossiers = $repo->findAll();
        $data = [];

        foreach ($dossiers as $d) {
            $data[] = [
                'id' => $d->getId(),
                'patient' => $d->getUser()->getNom().' '.$d->getUser()->getPrenom(),
                'groupeSanguin' => $d->getGroupeSanguin(),
                'imc' => $d->getIMC(),
                'risqueCardiaque' => $d->getRisqueCardiaque(),
            ];
        }

        return new JsonResponse($data);
    }

    #[Route('/api/dossiers/{id}', name: 'api_dossier_show', methods: ['GET'])]
    public function apiShow(DossierMedical $dossier): JsonResponse
    {
        return new JsonResponse([
            'id' => $dossier->getId(),
            'patient' => $dossier->getUser()->getNom().' '.$dossier->getUser()->getPrenom(),
            'groupeSanguin' => $dossier->getGroupeSanguin(),
            'antecedents' => $dossier->getAntecedents(),
            'allergies' => $dossier->getAllergies(),
            'poids' => $dossier->getPoids(),
            'taille' => $dossier->getTaille(),
            'imc' => $dossier->getIMC(),
            'tensionSystolique' => $dossier->getTensionSystolique(),
            'tensionDiastolique' => $dossier->getTensionDiastolique(),
            'frequenceCardiaque' => $dossier->getFrequenceCardiaque(),
            'risqueCardiaque' => $dossier->getRisqueCardiaque(),
        ]);
    }
}