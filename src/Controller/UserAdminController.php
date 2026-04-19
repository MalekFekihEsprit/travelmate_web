<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\AdminUserType;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\UX\Chartjs\Builder\ChartBuilderInterface;
use Symfony\UX\Chartjs\Model\Chart;

#[Route('/admin/users')]
#[IsGranted('ROLE_ADMIN')]
class UserAdminController extends AbstractController
{
    #[Route('', name: 'app_admin_users', methods: ['GET'])]
    public function index(Request $request, UserRepository $userRepository): Response
    {
        $search = trim((string) $request->query->get('q', ''));
        $role = trim((string) $request->query->get('role', ''));

        if (!in_array($role, ['', 'ADMIN', 'USER'], true)) {
            $role = '';
        }

        $users = $userRepository->searchForAdmin($search, $role ?: null);

        if ($request->isXmlHttpRequest()) {
            return $this->render('user_admin/_users_results.html.twig', [
                'users' => $users,
            ]);
        }

        return $this->render('user_admin/index.html.twig', [
            'users' => $users,
            'search' => $search,
            'selectedRole' => $role,
        ]);
    }

    #[Route('/new', name: 'app_admin_users_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        EntityManagerInterface $entityManager,
        UserRepository $userRepository,
        UserPasswordHasherInterface $passwordHasher,
        SluggerInterface $slugger
    ): Response {
        $user = new User();
        $user->setRole('USER');
        $user->setCreated_at(new \DateTime());
        $user->setIsVerified(true);

        $form = $this->createForm(AdminUserType::class, $user, ['is_edit' => false]);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            $emailValue = mb_strtolower(trim((string) $user->getEmail()));
            $existingUser = $userRepository->findOneBy(['email' => $emailValue]);
            if ($existingUser) {
                $form->get('email')->addError(new FormError('Cette adresse email est déjà utilisée.'));
            }
        }

        if ($form->isSubmitted() && $form->isValid()) {
            $user->setEmail(mb_strtolower(trim((string) $user->getEmail())));
            $user->setVerificationCode(null);

            $plainPassword = (string) $form->get('plainPassword')->getData();
            $user->setMotDePasse($passwordHasher->hashPassword($user, $plainPassword));

            // === PHOTO HANDLING (same as ProfileController) ===
            $photoFile = $form->get('photoFile')->getData();
            $photoUrl = $form->get('photoUrl')->getData();

            if ($photoFile && $photoUrl) {
                $this->addFlash('error', 'Veuillez choisir UNE SEULE méthode : soit uploader un fichier, soit entrer une URL, mais pas les deux.');
                return $this->redirectToRoute('app_admin_users_new');
            }

            if ($photoFile) {
                $originalFilename = pathinfo($photoFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename . '-' . uniqid() . '.' . $photoFile->guessExtension();

                try {
                    $photoFile->move(
                        $this->getParameter('kernel.project_dir') . '/public/uploads/profiles',
                        $newFilename
                    );
                    $user->setPhotoFileName($newFilename);
                    $user->setPhotoUrl(null);
                } catch (FileException $e) {
                    $this->addFlash('error', 'La photo n’a pas pu être importée.');
                    return $this->redirectToRoute('app_admin_users_new');
                }
            } elseif ($photoUrl) {
                $user->setPhotoUrl($photoUrl);
                $user->setPhotoFileName(null);
            }

            $entityManager->persist($user);
            $entityManager->flush();

            $this->addFlash('success', 'L’utilisateur a été créé avec succès.');
            return $this->redirectToRoute('app_admin_users');
        }

        return $this->render('user_admin/form.html.twig', [
            'pageTitle' => 'Ajouter un utilisateur',
            'pageSubtitle' => 'Créez un nouveau compte utilisateur ou administrateur.',
            'form' => $form,
            'userEntity' => $user,
            'isEdit' => false,
            'profileImage' => $user->getProfileImage(),
        ]);
    }

    #[Route('/{id}/edit', name: 'app_admin_users_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(
        User $user,
        Request $request,
        EntityManagerInterface $entityManager,
        UserRepository $userRepository,
        UserPasswordHasherInterface $passwordHasher,
        SluggerInterface $slugger
    ): Response {
        $originalEmail = $user->getEmail();
        
        // Store old photo info
        $oldPhotoFileName = $user->getPhotoFileName();
        $oldPhotoUrl = $user->getPhotoUrl();

        $form = $this->createForm(AdminUserType::class, $user, ['is_edit' => true]);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            $emailValue = mb_strtolower(trim((string) $user->getEmail()));
            if ($emailValue !== mb_strtolower((string) $originalEmail)) {
                $existingUser = $userRepository->findOneBy(['email' => $emailValue]);
                if ($existingUser && $existingUser->getId() !== $user->getId()) {
                    $form->get('email')->addError(new FormError('Cette adresse email est déjà utilisée.'));
                }
            }
        }

        if ($form->isSubmitted() && $form->isValid()) {
            $user->setEmail(mb_strtolower(trim((string) $user->getEmail())));

            $plainPassword = trim((string) $form->get('plainPassword')->getData());
            if ($plainPassword !== '') {
                $user->setMotDePasse($passwordHasher->hashPassword($user, $plainPassword));
            }

            // === PHOTO HANDLING (exactly like ProfileController) ===
            $photoFile = $form->get('photoFile')->getData();
            $photoUrl = $form->get('photoUrl')->getData();

            if ($photoFile && $photoUrl) {
                $this->addFlash('error', 'Veuillez choisir UNE SEULE méthode : soit uploader un fichier, soit entrer une URL, mais pas les deux.');
                return $this->redirectToRoute('app_admin_users_edit', ['id' => $user->getId()]);
            }

            if ($photoFile) {
                if ($oldPhotoFileName) {
                    $this->deleteOldProfilePhoto($oldPhotoFileName);
                }

                $originalFilename = pathinfo($photoFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename . '-' . uniqid() . '.' . $photoFile->guessExtension();

                try {
                    $photoFile->move(
                        $this->getParameter('kernel.project_dir') . '/public/uploads/profiles',
                        $newFilename
                    );
                    $user->setPhotoFileName($newFilename);
                    $user->setPhotoUrl(null);
                    $this->addFlash('success', 'Photo de profil mise à jour.');
                } catch (FileException $e) {
                    $this->addFlash('error', 'La photo n’a pas pu être importée.');
                    return $this->redirectToRoute('app_admin_users_edit', ['id' => $user->getId()]);
                }
            } elseif ($photoUrl) {
                if ($photoUrl !== $oldPhotoUrl || $oldPhotoFileName) {
                    if ($oldPhotoFileName) {
                        $this->deleteOldProfilePhoto($oldPhotoFileName);
                    }
                    $user->setPhotoUrl($photoUrl);
                    $user->setPhotoFileName(null);
                    $this->addFlash('success', 'URL de la photo mise à jour.');
                }
            } else {
                if ($oldPhotoFileName) {
                    $this->deleteOldProfilePhoto($oldPhotoFileName);
                }
                $user->setPhotoFileName(null);
                $user->setPhotoUrl(null);
                $this->addFlash('success', 'Photo de profil supprimée.');
            }

            $entityManager->flush();
            $this->addFlash('success', 'L’utilisateur a été modifié avec succès.');
            return $this->redirectToRoute('app_admin_users');
        }

        return $this->render('user_admin/form.html.twig', [
            'pageTitle' => 'Modifier un utilisateur',
            'pageSubtitle' => 'Mettez à jour les informations du compte sélectionné.',
            'form' => $form,
            'userEntity' => $user,
            'isEdit' => true,
            'profileImage' => $user->getProfileImage(),
        ]);
    }

    /**
     * Delete old profile photo from filesystem
     */
    private function deleteOldProfilePhoto(?string $fileName): void
    {
        if (!$fileName) {
            return;
        }
        $filesystem = new Filesystem();
        $oldFilePath = $this->getParameter('kernel.project_dir') . '/public/uploads/profiles/' . $fileName;
        if ($filesystem->exists($oldFilePath)) {
            try {
                $filesystem->remove($oldFilePath);
            } catch (\Exception $e) {
                // Log error if needed
            }
        }
    }

    #[Route('/{id}/delete', name: 'app_admin_users_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(
        User $user,
        Request $request,
        EntityManagerInterface $entityManager
    ): Response {
        if (!$this->isCsrfTokenValid('delete_user_'.$user->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_admin_users');
        }

        if ($this->getUser() instanceof User && $this->getUser()->getId() === $user->getId()) {
            $this->addFlash('error', 'Vous ne pouvez pas supprimer votre propre compte administrateur depuis cette page.');
            return $this->redirectToRoute('app_admin_users');
        }

        // Delete profile photo before removing user
        if ($user->getPhotoFileName()) {
            $this->deleteOldProfilePhoto($user->getPhotoFileName());
        }

        $entityManager->remove($user);
        $entityManager->flush();

        $this->addFlash('success', 'L’utilisateur a été supprimé avec succès.');
        return $this->redirectToRoute('app_admin_users');
    }

    #[Route('/stats', name: 'app_admin_users_stats', methods: ['GET'])]
    public function stats(
        Request $request,
        UserRepository $userRepository,
        ChartBuilderInterface $chartBuilder
    ): Response {
        // Allow negative offsets for future weeks? Or just use positive for past
        $offset = (int) $request->query->get('offset', 0);
        $isAjax = $request->isXmlHttpRequest() || $request->query->has('ajax');
        
        // Core stats
        $totalUsers = $userRepository->countAllUsers();
        $adminCount = $userRepository->countByRole('ADMIN');
        $userCount = $userRepository->countByRole('USER');

        $adminPercentage = $totalUsers ? round(($adminCount / $totalUsers) * 100, 1) : 0;
        $userPercentage = $totalUsers ? round(($userCount / $totalUsers) * 100, 1) : 0;

        // Verification stats
        $verifiedCount = $userRepository->countVerifiedUsers();
        $unverifiedCount = $userRepository->countUnverifiedUsers();
        $verifiedPercentage = $totalUsers ? round(($verifiedCount / $totalUsers) * 100, 1) : 0;
        $unverifiedPercentage = $totalUsers ? round(($unverifiedCount / $totalUsers) * 100, 1) : 0;

        // Activity stats
        $activeLast30 = method_exists($userRepository, 'countActiveUsersLastDays')
            ? $userRepository->countActiveUsersLastDays(30)
            : 0;
        $activePercentage = $totalUsers ? round(($activeLast30 / $totalUsers) * 100, 1) : 0;

        // 7-day navigable window
        // Calculate date range correctly
        $endDate = (new \DateTimeImmutable('today'))->modify(sprintf('-%d days', $offset));
        $startDate = $endDate->modify('-6 days');
        $registrationsByDay = $userRepository->getRegistrationsWindowByDateRange($startDate, $endDate);
        $avgDailyRegistrations = $registrationsByDay ? round(array_sum($registrationsByDay) / count($registrationsByDay), 1) : 0;

        $peakDay = null;
        if (!empty($registrationsByDay)) {
            $peakValue = max($registrationsByDay);
            $peakDay = array_keys($registrationsByDay, $peakValue)[0] ?? null;
        }

        // 30-day current trend
        $registrationsLast30 = $userRepository->getRegistrationsByDayExtended(30, 0);

        // Growth
        $growth = $userRepository->getRegistrationGrowth();

        // Age stats
        $ageDistribution = $userRepository->getAgeDistribution();
        $averageAge = $userRepository->getAverageAge();
        $youngestAge = $userRepository->getYoungestAge();
        $oldestAge = $userRepository->getOldestAge();
        $birthYears = $userRepository->getUsersByBirthYear();
        $totalWithAge = array_sum($ageDistribution) - ($ageDistribution['unknown'] ?? 0);

        // Format for display
        $windowStart = $startDate->format('d/m/Y');
        $windowEnd = $endDate->format('d/m/Y');
        $canGoForward = $offset > 0;

        $dailyLabels = array_map(
            fn(string $date) => (new \DateTimeImmutable($date))->format('d/m'),
            array_keys($registrationsByDay)
        );
        $dailyValues = array_values($registrationsByDay);

        if ($isAjax) {
            return $this->json([
                'offset' => $offset,
                'windowStart' => $windowStart,
                'windowEnd' => $windowEnd,
                'labels' => $dailyLabels,
                'values' => $dailyValues,
                'canGoForward' => $canGoForward,
                'avgDailyRegistrations' => $avgDailyRegistrations,
                'peakDay' => $peakDay ? (new \DateTimeImmutable($peakDay))->format('d/m') : 'N/A',
            ]);
        }

        // ===== Static charts =====

        $roleChart = $chartBuilder->createChart(Chart::TYPE_DOUGHNUT);
        $roleChart->setData([
            'labels' => ['Administrateurs', 'Utilisateurs'],
            'datasets' => [[
                'label' => 'Répartition des rôles',
                'data' => [$adminCount, $userCount],
                'backgroundColor' => ['#c46f4b', '#2f7f79'],
                'borderWidth' => 0,
            ]],
        ]);
        $roleChart->setOptions([
            'plugins' => ['legend' => ['position' => 'bottom']],
            'maintainAspectRatio' => false,
        ]);

        $verificationChart = $chartBuilder->createChart(Chart::TYPE_DOUGHNUT);
        $verificationChart->setData([
            'labels' => ['Vérifiés', 'Non vérifiés'],
            'datasets' => [[
                'label' => 'Vérification des comptes',
                'data' => [$verifiedCount, $unverifiedCount],
                'backgroundColor' => ['#2f7f79', '#ddbf8c'],
                'borderWidth' => 0,
            ]],
        ]);
        $verificationChart->setOptions([
            'plugins' => ['legend' => ['position' => 'bottom']],
            'maintainAspectRatio' => false,
        ]);

        $trend30Chart = $chartBuilder->createChart(Chart::TYPE_LINE);
        $trend30Chart->setData([
            'labels' => array_map(
                fn(string $date) => (new \DateTimeImmutable($date))->format('d/m'),
                array_keys($registrationsLast30)
            ),
            'datasets' => [[
                'label' => 'Inscriptions sur 30 jours',
                'data' => array_values($registrationsLast30),
                'borderColor' => '#2f7f79',
                'backgroundColor' => 'rgba(47,127,121,0.12)',
                'fill' => true,
                'tension' => 0.35,
                'pointRadius' => 3,
            ]],
        ]);
        $trend30Chart->setOptions([
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'ticks' => ['precision' => 0],
                ],
            ],
            'plugins' => [
                'legend' => [
                    'display' => true,
                    'position' => 'bottom',
                ],
            ],
            'maintainAspectRatio' => false,
        ]);

        $ageLabels = [];
        $ageValues = [];
        foreach ($ageDistribution as $range => $count) {
            if ($range === 'unknown') {
                continue;
            }

            $label = match ($range) {
                'under-18' => 'Moins de 18 ans',
                default => $range . ' ans',
            };

            $ageLabels[] = $label;
            $ageValues[] = $count;
        }

        $ageDistributionChart = $chartBuilder->createChart(Chart::TYPE_BAR);
        $ageDistributionChart->setData([
            'labels' => $ageLabels,
            'datasets' => [[
                'label' => 'Utilisateurs par tranche d’âge',
                'data' => $ageValues,
                'backgroundColor' => '#ddbf8c',
                'borderRadius' => 10,
            ]],
        ]);
        $ageDistributionChart->setOptions([
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'ticks' => ['precision' => 0],
                ],
            ],
            'plugins' => [
                'legend' => ['display' => false],
            ],
            'maintainAspectRatio' => false,
        ]);

        $birthYearsChart = $chartBuilder->createChart(Chart::TYPE_LINE);
        $birthYearsChart->setData([
            'labels' => array_keys($birthYears),
            'datasets' => [[
                'label' => 'Utilisateurs par année de naissance',
                'data' => array_values($birthYears),
                'borderColor' => '#c46f4b',
                'backgroundColor' => 'rgba(196,111,75,0.10)',
                'fill' => true,
                'tension' => 0.25,
                'pointRadius' => 3,
            ]],
        ]);
        $birthYearsChart->setOptions([
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'ticks' => ['precision' => 0],
                ],
            ],
            'plugins' => [
                'legend' => [
                    'display' => true,
                    'position' => 'bottom',
                ],
            ],
            'maintainAspectRatio' => false,
        ]);

        return $this->render('user_admin/stats.html.twig', [
            'totalUsers' => $totalUsers,
            'adminCount' => $adminCount,
            'userCount' => $userCount,
            'adminPercentage' => $adminPercentage,
            'userPercentage' => $userPercentage,

            'verifiedCount' => $verifiedCount,
            'unverifiedCount' => $unverifiedCount,
            'verifiedPercentage' => $verifiedPercentage,
            'unverifiedPercentage' => $unverifiedPercentage,

            'activeLast30' => $activeLast30,
            'activePercentage' => $activePercentage,

            'avgDailyRegistrations' => $avgDailyRegistrations,
            'peakDay' => $peakDay,
            'growth' => $growth,

            'averageAge' => $averageAge,
            'youngestAge' => $youngestAge,
            'oldestAge' => $oldestAge,
            'totalWithAge' => $totalWithAge,

            'offset' => $offset,
            'windowStart' => $windowStart,
            'windowEnd' => $windowEnd,
            'dailyLabels' => $dailyLabels,
            'dailyValues' => $dailyValues,

            'roleChart' => $roleChart,
            'verificationChart' => $verificationChart,
            'trend30Chart' => $trend30Chart,
            'ageDistributionChart' => $ageDistributionChart,
            'birthYearsChart' => $birthYearsChart,
        ]);
    }
}