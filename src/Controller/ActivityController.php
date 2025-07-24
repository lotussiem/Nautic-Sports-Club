<?php

namespace App\Controller;

use App\Entity\Activity;
use App\Entity\Category;
use App\Form\ActivityFormType;
use App\Form\SearchFormType;
use App\Repository\ActivityRepository;
use App\Repository\CategoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class ActivityController extends AbstractController
{
    private EntityManagerInterface $entityManager;
    private ActivityRepository $activityRepository;
    private CategoryRepository $categoryRepository;
    private LoggerInterface $logger;
    private CacheInterface $cache;

    public function __construct(
        EntityManagerInterface $entityManager,
        ActivityRepository $activityRepository,
        CategoryRepository $categoryRepository,
        LoggerInterface $logger,
        CacheInterface $cache
    ) {
        $this->entityManager = $entityManager;
        $this->activityRepository = $activityRepository;
        $this->categoryRepository = $categoryRepository;
        $this->logger = $logger;
        $this->cache = $cache;
    }

    #[Route('/activity', name: 'activity_public_index')]
    public function publicIndex(Request $request, PaginatorInterface $paginator): Response
    {
        try {
            // Fetch all categories
            $categories = $this->categoryRepository->findAll();

            // Initialize query builder
            $queryBuilder = $this->activityRepository->createQueryBuilder('a')
                ->leftJoin('a.category', 'c')
                ->addSelect('c')
                ->orderBy('a.name', 'ASC');

            // Handle filters
            $categoryId = $request->query->get('category');
            $place = $request->query->get('place');
            $difficulty = $request->query->get('difficulty');
            $query = $request->query->get('query');
            $selectedCategory = null;
            $searchPerformed = false;

            if ($categoryId && is_numeric($categoryId)) {
                $selectedCategory = $this->categoryRepository->find($categoryId);
                if ($selectedCategory) {
                    $queryBuilder->andWhere('a.category = :category')
                        ->setParameter('category', $selectedCategory);
                } else {
                    $this->addFlash('warning', 'Category not found. Showing all activities.');
                }
            }

            if ($place && $place !== 'all') {
                $queryBuilder->andWhere('a.place = :place')
                    ->setParameter('place', $place);
            }

            if ($difficulty && $difficulty !== 'all' && is_numeric($difficulty)) {
                $queryBuilder->andWhere('a.difficulty = :difficulty')
                    ->setParameter('difficulty', (int) $difficulty);
            }

            if ($query && strlen(trim($query)) >= 2) {
                $queryBuilder->andWhere('LOWER(a.name) LIKE :searchQuery OR (a.description IS NOT NULL AND LOWER(a.description) LIKE :searchQuery)')
                    ->setParameter('searchQuery', '%' . strtolower(trim($query)) . '%');
                $searchPerformed = true;
            }

            // Paginate the query
            $pagination = $paginator->paginate(
                $queryBuilder->getQuery(),
                $request->query->getInt('page', 1),
                9 // Items per page (3 per row, 3 rows)
            );

            // Fetch places and difficulty levels
            $places = array_column(
                $this->activityRepository->createQueryBuilder('a')
                    ->select('DISTINCT a.place')
                    ->where('a.place IS NOT NULL')
                    ->orderBy('a.place', 'ASC')
                    ->getQuery()
                    ->getResult(),
                'place'
            );

            $difficulties = array_column(
                $this->activityRepository->createQueryBuilder('a')
                    ->select('DISTINCT a.difficulty')
                    ->where('a.difficulty IS NOT NULL')
                    ->orderBy('a.difficulty', 'ASC')
                    ->getQuery()
                    ->getResult(),
                'difficulty'
            );

            // Geocode and fetch weather data
            $httpClient = HttpClient::create();
            $coordinates = [];
            $weatherData = [];

            foreach ($pagination as $activity) {
                if (!$activity->getPlace()) {
                    continue;
                }

                // Cache geocoding result (Nominatim)
                $cacheKey = 'geocode_' . md5($activity->getPlace());
                $coords = $this->cache->get($cacheKey, function (ItemInterface $item) use ($httpClient, $activity) {
                    $item->expiresAfter(86400); // Cache for 1 day
                    $response = $httpClient->request('GET', 'https://nominatim.openstreetmap.org/search', [
                        'query' => [
                            'q' => $activity->getPlace(),
                            'format' => 'json',
                            'limit' => 1,
                        ],
                        'headers' => ['User-Agent' => 'NauticalSportsApp/1.0'], // Required by Nominatim
                    ]);
                    $data = $response->toArray();
                    if (empty($data)) {
                        $this->logger->warning('No geocoding data for place: ' . $activity->getPlace());
                        return ['lat' => null, 'lon' => null];
                    }
                    return [
                        'lat' => $data[0]['lat'] ?? null,
                        'lon' => $data[0]['lon'] ?? null,
                    ];
                });

                if ($coords['lat'] && $coords['lon']) {
                    $coordinates[$activity->getId()] = $coords;

                    // Fetch weather from Open-Meteo
                    $weatherCacheKey = 'weather_' . md5($activity->getPlace() . '_' . $coords['lat'] . '_' . $coords['lon']);
                    $weather = $this->cache->get($weatherCacheKey, function (ItemInterface $item) use ($httpClient, $coords) {
                        $item->expiresAfter(3600); // Cache for 1 hour
                        $response = $httpClient->request('GET', 'https://api.open-meteo.com/v1/forecast', [
                            'query' => [
                                'latitude' => $coords['lat'],
                                'longitude' => $coords['lon'],
                                'current_weather' => true,
                            ],
                        ]);
                        $data = $response->toArray();
                        return [
                            'temp' => $data['current_weather']['temperature'] ?? null,
                            'description' => $data['current_weather']['weathercode'] ?? null, // Use weathercode for conditions
                        ];
                    });

                    $weatherData[$activity->getId()] = $weather;
                }
            }

            // Log data
            $this->logger->info('Public index fetched activities: ' . count($pagination) . ', categories: ' . count($categories));

            return $this->render('activity/index.html.twig', [
                'activities' => $pagination,
                'categories' => $categories,
                'places' => $places,
                'difficulties' => $difficulties,
                'selectedCategory' => $selectedCategory,
                'searchPerformed' => $searchPerformed,
                'coordinates' => $coordinates,
                'weatherData' => $weatherData,
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Error in ActivityController::publicIndex: ' . $e->getMessage() . ' | Trace: ' . $e->getTraceAsString());
            $this->addFlash('error', 'An error occurred while fetching data.');
            return $this->render('activity/index.html.twig', [
                'activities' => [],
                'categories' => [],
                'places' => [],
                'difficulties' => [],
                'selectedCategory' => null,
                'searchPerformed' => false,
                'coordinates' => [],
                'weatherData' => [],
            ]);
        }
    }

    #[Route('/test-fetch', name: 'test_fetch')]
    public function testFetch(ActivityRepository $activityRepository, CategoryRepository $categoryRepository): Response
    {
        $activities = $activityRepository->findAll();
        $categories = $categoryRepository->findAll();
        return new Response(
            '<h1>Activities: ' . count($activities) . '</h1>' .
            '<h1>Categories: ' . count($categories) . '</h1>' .
            '<pre>' . print_r(array_map(fn($a) => $a->getName(), $activities), true) . '</pre>' .
            '<pre>' . print_r(array_map(fn($c) => $c->getName(), $categories), true) . '</pre>'
        );
    }

    #[Route('/activity/suggestions', name: 'activity_suggestions', methods: ['GET'])]
    public function suggestions(Request $request): JsonResponse
    {
        try {
            $query = $request->query->get('query', '');
            if (strlen(trim($query)) < 2) {
                return new JsonResponse([]);
            }

            $cacheKey = 'activity_suggestions_' . md5($query);
            $suggestions = $this->cache->get($cacheKey, function (ItemInterface $item) use ($query) {
                $item->expiresAfter(300);
                $activities = $this->activityRepository->findBySearchQuery($query);
                return array_map(function (Activity $activity) {
                    return [
                        'id' => $activity->getId(),
                        'name' => $activity->getName(),
                        'description' => $activity->getDescription() ?? '',
                    ];
                }, $activities);
            });

            return new JsonResponse($suggestions);
        } catch (\Exception $e) {
            $this->logger->error('Error in ActivityController::suggestions: ' . $e->getMessage() . ' | Trace: ' . $e->getTraceAsString());
            return new JsonResponse([], 500);
        }
    }

    #[Route('/admin/activity', name: 'admin_activity_admin_index', methods: ['GET'])]
    public function adminIndex(Request $request): Response
    {
        try {
            $categoryId = $request->query->get('category');
            if ($categoryId && is_numeric($categoryId)) {
                $category = $this->categoryRepository->find($categoryId);
                if ($category) {
                    $activities = $this->activityRepository->findBy(['category' => $category], ['name' => 'ASC']);
                } else {
                    $this->addFlash('warning', 'Category not found. Showing all activities.');
                    $activities = $this->activityRepository->findAll();
                }
            } else {
                $activities = $this->activityRepository->findAll();
            }

            $categories = $this->categoryRepository->findAll();
            $this->logger->info('Admin index fetched activities: ' . count($activities) . ', categories: ' . count($categories));

            return $this->render('activity/index.html.twig', [
                'activities' => $activities,
                'categories' => $categories,
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Error in ActivityController::adminIndex: ' . $e->getMessage() . ' | Trace: ' . $e->getTraceAsString());
            $this->addFlash('error', 'An error occurred while fetching data.');
            return $this->render('activity/index.html.twig', [
                'activities' => [],
                'categories' => [],
            ]);
        }
    }

    #[Route('/manage/activities', name: 'activity_manage', methods: ['GET'])]
    public function manage(Request $request): Response
    {
        try {
            $activities = $this->activityRepository->findAll();
            $categories = $this->categoryRepository->findAll();
            $this->logger->info('Manage fetched activities: ' . count($activities) . ', categories: ' . count($categories));

            return $this->render('manageAct/index.html.twig', [
                'activities' => $activities,
                'categories' => $categories,
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Error in ActivityController::manage: ' . $e->getMessage() . ' | Trace: ' . $e->getTraceAsString());
            $this->addFlash('error', 'An error occurred while fetching activities.');
            return $this->render('manageAct/index.html.twig', [
                'activities' => [],
                'categories' => [],
            ]);
        }
    }

    #[Route('/admin/activity/manage', name: 'admin_activity_admin_manage', methods: ['GET', 'POST'])]
    public function adminManage(Request $request): Response
    {
        try {
            $activities = $this->activityRepository->findAll();
            $categories = $this->categoryRepository->findAll();
            $newForm = $this->createForm(ActivityFormType::class, new Activity());
            $editForm = null;
            $editActivity = $request->query->get('edit');

            if ($editActivity && is_numeric($editActivity)) {
                $activityToEdit = $this->activityRepository->find($editActivity);
                if ($activityToEdit) {
                    $editForm = $this->createForm(ActivityFormType::class, $activityToEdit);
                    $editForm->handleRequest($request);
                    if ($editForm->isSubmitted() && $editForm->isValid()) {
                        try {
                            $this->logger->info('Updating activity: ' . $activityToEdit->getName());
                            $this->entityManager->flush();
                            $this->logger->info('Activity updated successfully with ID: ' . $activityToEdit->getId());
                            $this->addFlash('success', 'Activity updated successfully!');
                            return $this->redirectToRoute('admin_activity_admin_manage');
                        } catch (\Exception $e) {
                            $this->logger->error('Error updating activity: ' . $e->getMessage());
                            $this->addFlash('error', 'An error occurred while updating the activity.');
                        }
                    }
                } else {
                    $this->addFlash('error', 'Activity not found.');
                    return $this->redirectToRoute('admin_activity_admin_manage');
                }
            }

            if (!$editForm || !$editForm->isSubmitted()) {
                $newForm->handleRequest($request);
                if ($newForm->isSubmitted() && $newForm->isValid()) {
                    try {
                        $this->logger->info('Creating new activity: ' . $newForm->getData()->getName());
                        $this->entityManager->persist($newForm->getData());
                        $this->entityManager->flush();
                        $this->logger->info('Activity created successfully with ID: ' . $newForm->getData()->getId());
                        $this->addFlash('success', 'Activity created successfully!');
                        return $this->redirectToRoute('admin_activity_admin_manage');
                    } catch (\Exception $e) {
                        $this->logger->error('Error creating activity: ' . $e->getMessage());
                        $this->addFlash('error', 'An error occurred while creating the activity.');
                    }
                }
            }

            $this->logger->info('Admin manage fetched activities: ' . count($activities) . ', categories: ' . count($categories));

            return $this->render('manageAct/manageAdmin.html.twig', [
                'activities' => $activities,
                'categories' => $categories,
                'newForm' => $newForm->createView(),
                'editForm' => $editForm ? $editForm->createView() : null,
                'editActivityId' => $editActivity,
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Error in ActivityController::adminManage: ' . $e->getMessage() . ' | Trace: ' . $e->getTraceAsString());
            $this->addFlash('error', 'An error occurred while fetching activities.');
            return $this->render('manageAct/manageAdmin.html.twig', [
                'activities' => [],
                'categories' => [],
                'newForm' => $this->createForm(ActivityFormType::class, new Activity())->createView(),
                'editForm' => null,
                'editActivityId' => null,
            ]);
        }
    }

    #[Route('/admin/activity/{id}', name: 'admin_activity_admin_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function adminDelete(Request $request, Activity $activity): Response
    {
        if ($this->isCsrfTokenValid('delete' . $activity->getId(), $request->request->get('_token'))) {
            try {
                $this->logger->info('Deleting activity: ' . $activity->getName());
                $this->entityManager->remove($activity);
                $this->entityManager->flush();
                $this->logger->info('Activity deleted successfully with ID: ' . $activity->getId());
                $this->addFlash('success', 'Activity deleted successfully!');
            } catch (\Exception $e) {
                $this->logger->error('Error deleting activity: ' . $e->getMessage());
                $this->addFlash('error', 'An error occurred while deleting the activity.');
            }
        } else {
            $this->addFlash('error', 'Invalid CSRF token.');
        }

        return $this->redirectToRoute('admin_activity_admin_manage');
    }

    #[Route('/manage/activities/{id}', name: 'activity_manage_detail', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function manageDetail(int $id, ActivityRepository $activityRepository): Response
    {
        $this->logger->info('Fetching activity with ID: ' . $id);
        $activity = $activityRepository->findWithCategory($id);

        if (!$activity) {
            $this->logger->error('Activity not found for ID: ' . $id);
            $this->addFlash('error', 'Activity not found.');
            return $this->redirectToRoute('activity_manage');
        }

        $form = $this->createForm(ActivityFormType::class, $activity);

        $this->logger->info('Activity found: ' . ($activity->getName() ?? 'No name'));
        return $this->render('manageAct/detail.html.twig', [
            'activity' => $activity,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/manage/activities/{id}', name: 'activity_manage_update', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function manageUpdate(Request $request, Activity $activity): Response
    {
        $form = $this->createForm(ActivityFormType::class, $activity);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $this->logger->info('Form is valid, updating activity: ' . $activity->getName());
                $this->entityManager->flush();
                $this->logger->info('Activity updated successfully in database for ID: ' . $activity->getId());
                $this->addFlash('success', 'Activity updated successfully!');
            } catch (\Exception $e) {
                $this->logger->error('Error updating activity: ' . $e->getMessage());
                $this->addFlash('error', 'An error occurred while updating the activity.');
            }
        } else {
            $this->logger->warning('Form submission invalid or not submitted');
            foreach ($form->getErrors(true) as $error) {
                $this->logger->warning('Form error: ' . $error->getMessage());
                $this->addFlash('error', $error->getMessage());
            }
        }

        return $this->redirectToRoute('activity_manage_detail', ['id' => $activity->getId()]);
    }

    #[Route('/admin/activity/new', name: 'admin_activity_admin_new', methods: ['GET', 'POST'])]
    public function adminNew(Request $request): Response
    {
        $activity = new Activity();
        $categories = $this->categoryRepository->findAll();

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('activity_new', $request->request->get('_token'))) {
                $this->addFlash('error', 'Invalid CSRF token.');
                return $this->render('activity/new.html.twig', [
                    'activity' => $activity,
                    'categories' => $categories,
                ]);
            }

            $name = $request->request->get('name');
            $description = $request->request->get('description');
            $difficulty = $request->request->get('difficulty');
            $image_file = $request->request->get('image_file');
            $place = $request->request->get('place');
            $categoryId = $request->request->get('category');

            if (empty($name) || empty($categoryId)) {
                $this->addFlash('error', 'Name and category are required.');
                return $this->render('activity/new.html.twig', [
                    'activity' => $activity,
                    'categories' => $categories,
                ]);
            }

            if ($image_file && !filter_var($image_file, FILTER_VALIDATE_URL)) {
                $this->addFlash('error', 'The image file must be a valid URL.');
                return $this->render('activity/new.html.twig', [
                    'activity' => $activity,
                    'categories' => $categories,
                ]);
            }

            $category = $this->categoryRepository->find($categoryId);
            if (!$category) {
                $this->addFlash('error', 'Selected category not found.');
                return $this->render('activity/new.html.twig', [
                    'activity' => $activity,
                    'categories' => $categories,
                ]);
            }

            $activity->setName($name);
            $activity->setDescription($description);
            $activity->setDifficulty($difficulty);
            $activity->setImage_file($image_file);
            $activity->setPlace($place);
            $activity->setCategory($category);

            try {
                $this->entityManager->persist($activity);
                $this->entityManager->flush();
                $this->addFlash('success', 'Activity created successfully!');
                return $this->redirectToRoute('admin_activity_admin_index');
            } catch (\Exception $e) {
                $this->addFlash('error', 'An error occurred while creating the activity.');
            }
        }

        return $this->render('activity/new.html.twig', [
            'activity' => $activity,
            'categories' => $categories,
        ]);
    }

    #[Route('/admin/activity/{id}/edit', name: 'admin_activity_admin_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function adminEdit(Request $request, Activity $activity): Response
    {
        $categories = $this->categoryRepository->findAll();

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('activity_edit', $request->request->get('_token'))) {
                $this->addFlash('error', 'Invalid CSRF token.');
                return $this->redirectToRoute('admin_activity_admin_edit', ['id' => $activity->getId()]);
            }

            $name = $request->request->get('name');
            $description = $request->request->get('description');
            $difficulty = $request->request->get('difficulty');
            $image_file = $request->request->get('image_file');
            $place = $request->request->get('place');
            $categoryId = $request->request->get('category');

            if (empty($name) || empty($categoryId)) {
                $this->addFlash('error', 'Name and category are required.');
                return $this->render('activity/edit.html.twig', [
                    'activity' => $activity,
                    'categories' => $categories,
                ]);
            }

            if ($image_file && !filter_var($image_file, FILTER_VALIDATE_URL)) {
                $this->addFlash('error', 'The image file must be a valid URL.');
                return $this->render('activity/edit.html.twig', [
                    'activity' => $activity,
                    'categories' => $categories,
                ]);
            }

            $category = $this->categoryRepository->find($categoryId);
            if (!$category) {
                $this->addFlash('error', 'Selected category not found.');
                return $this->render('activity/edit.html.twig', [
                    'activity' => $activity,
                    'categories' => $categories,
                ]);
            }

            $activity->setName($name);
            $activity->setDescription($description);
            $activity->setDifficulty($difficulty);
            $activity->setImage_file($image_file);
            $activity->setPlace($place);
            $activity->setCategory($category);

            try {
                $this->entityManager->flush();
                $this->addFlash('success', 'Activity updated successfully!');
                return $this->redirectToRoute('admin_activity_admin_index');
            } catch (\Exception $e) {
                $this->addFlash('error', 'An error occurred while updating the activity.');
            }
        }

        return $this->render('activity/edit.html.twig', [
            'activity' => $activity,
            'categories' => $categories,
        ]);
    }

    #[Route('/activity/{id}', name: 'activity_public_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function publicShow(Activity $activity): Response
    {
        return $this->render('activity/show.html.twig', [
            'activity' => $activity,
        ]);
    }

    #[Route('/activity/{id}/detail', name: 'activity_public_detail', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function publicDetail(int $id, ActivityRepository $activityRepository): Response
    {
        $this->logger->info('Fetching activity with ID: ' . $id);
        $activity = $activityRepository->findWithCategory($id);

        if (!$activity) {
            $this->logger->error('Activity not found for ID: ' . $id);
            $this->addFlash('error', 'Activity not found.');
            return $this->redirectToRoute('activity_public_index');
        }

        // Geocode and fetch weather data for the activity
        $httpClient = HttpClient::create();
        $weatherData = [];
        if ($activity->getPlace()) {
            $cacheKey = 'geocode_' . md5($activity->getPlace());
            $coords = $this->cache->get($cacheKey, function (ItemInterface $item) use ($httpClient, $activity) {
                $item->expiresAfter(86400); // Cache for 1 day
                $response = $httpClient->request('GET', 'https://nominatim.openstreetmap.org/search', [
                    'query' => [
                        'q' => $activity->getPlace(),
                        'format' => 'json',
                        'limit' => 1,
                    ],
                    'headers' => ['User-Agent' => 'NauticalSportsApp/1.0'], // Required by Nominatim
                ]);
                $data = $response->toArray();
                if (empty($data)) {
                    $this->logger->warning('No geocoding data for place: ' . $activity->getPlace());
                    return ['lat' => null, 'lon' => null];
                }
                return [
                    'lat' => $data[0]['lat'] ?? null,
                    'lon' => $data[0]['lon'] ?? null,
                ];
            });

            if ($coords['lat'] && $coords['lon']) {
                $weatherCacheKey = 'weather_' . md5($activity->getPlace() . '_' . $coords['lat'] . '_' . $coords['lon']);
                $weather = $this->cache->get($weatherCacheKey, function (ItemInterface $item) use ($httpClient, $coords) {
                    $item->expiresAfter(3600); // Cache for 1 hour
                    $response = $httpClient->request('GET', 'https://api.open-meteo.com/v1/forecast', [
                        'query' => [
                            'latitude' => $coords['lat'],
                            'longitude' => $coords['lon'],
                            'current_weather' => true,
                        ],
                    ]);
                    $data = $response->toArray();
                    return [
                        'temp' => $data['current_weather']['temperature'] ?? null,
                        'description' => $data['current_weather']['weathercode'] ?? null, // Use weathercode for conditions
                    ];
                });
                $weatherData[$activity->getId()] = $weather;
            }
        }

        $this->logger->info('Activity found: ' . ($activity->getName() ?? 'No name'));
        return $this->render('activity/detail.html.twig', [
            'activity' => $activity,
            'weatherData' => $weatherData,
        ]);
    }
}