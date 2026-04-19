<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\FaceEnrollmentFormType;
use App\Service\FaceRecognitionClient;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class ProfileFaceController extends AbstractController
{
    #[Route('/profile/face', name: 'app_profile_face', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        FaceRecognitionClient $faceRecognitionClient,
        EntityManagerInterface $entityManager
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        $form = $this->createForm(FaceEnrollmentFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var UploadedFile[]|null $captures */
            $captures = $form->get('faceCaptures')->getData();

            if (!$captures || count($captures) < 2) {
                $this->addFlash('error', 'Veuillez capturer au moins 2 images de votre visage.');
                return $this->redirectToRoute('app_profile_face');
            }

            try {
                // Debug: Log what we have
                error_log('Number of captures: ' . count($captures));
                foreach ($captures as $index => $capture) {
                    error_log('Capture ' . $index . ' is UploadedFile: ' . ($capture instanceof UploadedFile ? 'yes' : 'no'));
                    error_log('Capture ' . $index . ' path: ' . $capture->getPathname());
                }
                
                // The client expects an array of UploadedFile objects
                $embedding = $faceRecognitionClient->enrollFromUploadedFiles($captures);
                
                $user->setFaceEmbedding($faceRecognitionClient->encodeEmbedding($embedding));
                $entityManager->flush();

                $this->addFlash('success', 'Votre visage a été enregistré avec succès.');
                return $this->redirectToRoute('app_profile_face');
                
            } catch (\Throwable $e) {
                error_log('Error in face enrollment: ' . $e->getMessage());
                error_log('Stack trace: ' . $e->getTraceAsString());
                
                $this->addFlash('error', 'Impossible d\'enregistrer le visage : ' . $e->getMessage());
                return $this->redirectToRoute('app_profile_face');
            }
        }

        return $this->render('profile/face.html.twig', [
            'form' => $form,
            'hasFace' => !empty($user->getFaceEmbedding()),
        ]);
    }



    #[Route('/profile/face/remove', name: 'app_profile_face_remove', methods: ['POST'])]
    public function remove(EntityManagerInterface $entityManager): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $user->setFaceEmbedding(null);
        $entityManager->flush();

        $this->addFlash('success', 'Le visage enregistré a été supprimé.');
        return $this->redirectToRoute('app_profile_face');
    }
}