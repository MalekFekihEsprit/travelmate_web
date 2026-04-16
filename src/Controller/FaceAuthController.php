<?php

namespace App\Controller;

use App\Repository\UserRepository;
use App\Security\LoginFormAuthenticator;
use App\Service\FaceRecognitionClient;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\UserAuthenticatorInterface;

class FaceAuthController extends AbstractController
{
    #[Route('/login/face', name: 'app_face_login', methods: ['GET', 'POST'])]
    public function loginWithFace(
        Request $request,
        UserRepository $userRepository,
        FaceRecognitionClient $faceRecognitionClient,
        UserAuthenticatorInterface $userAuthenticator,
        LoginFormAuthenticator $loginFormAuthenticator
    ): Response {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_home');
        }

        if ($request->isMethod('POST')) {
            /** @var UploadedFile|null $faceImage */
            $faceImage = $request->files->get('faceImage');

            if (!$faceImage) {
                $this->addFlash('error', 'Veuillez capturer une image du visage.');
                return $this->redirectToRoute('app_face_login');
            }

            try {
                $probeEmbedding = $faceRecognitionClient->extractEmbeddingFromUploadedFile($faceImage);
            } catch (\Throwable $e) {
                $this->addFlash('error', 'Impossible de lire le visage : '.$e->getMessage());
                return $this->redirectToRoute('app_face_login');
            }

            $users = $userRepository->findVerifiedUsersWithFaceEmbedding();
            $bestUser = null;
            $bestScore = 0.0;
            $threshold = (float) $this->getParameter('face_match_threshold');

            foreach ($users as $user) {
                $stored = $faceRecognitionClient->decodeStoredEmbedding($user->getFaceEmbedding());

                if (!$stored) {
                    continue;
                }

                try {
                    $comparison = $faceRecognitionClient->compareEmbeddings($probeEmbedding, $stored, $threshold);

                    if ($comparison['similarity'] > $bestScore) {
                        $bestScore = $comparison['similarity'];
                        $bestUser = $user;
                    }
                } catch (\Throwable $e) {
                    continue;
                }
            }

            if (!$bestUser || $bestScore < $threshold) {
                $this->addFlash('error', 'Aucun visage correspondant n’a été reconnu.');
                return $this->redirectToRoute('app_face_login');
            }

            return $userAuthenticator->authenticateUser(
                $bestUser,
                $loginFormAuthenticator,
                $request
            );
        }

        return $this->render('security/face_login.html.twig');
    }
}