<?php

namespace App\Controller;

use App\Entity\Category;
use App\Form\CategoryFormType;
use App\Repository\CategoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Psr\Log\LoggerInterface;

#[Route('/admin/category')]
class CategoryController extends AbstractController
{
    private EntityManagerInterface $entityManager;
    private CategoryRepository $categoryRepository;

    public function __construct(
        EntityManagerInterface $entityManager,
        CategoryRepository $categoryRepository
    ) {
        $this->entityManager = $entityManager;
        $this->categoryRepository = $categoryRepository;
    }

    #[Route('/', name: 'admin_category_index', methods: ['GET', 'POST'])]
    public function index(Request $request, LoggerInterface $logger): Response
    {
        try {
            $categories = $this->categoryRepository->findAll();
            $editCategoryId = $request->query->get('edit');
            $newForm = null;
            $editForm = null;

            // Handle edit form
            if ($editCategoryId && is_numeric($editCategoryId)) {
                $categoryToEdit = $this->categoryRepository->find($editCategoryId);
                if ($categoryToEdit) {
                    $editForm = $this->createForm(CategoryFormType::class, $categoryToEdit);
                    $editForm->handleRequest($request);
                    if ($editForm->isSubmitted() && $editForm->isValid()) {
                        try {
                            $logger->info('Updating category: ' . $categoryToEdit->getName());
                            $this->entityManager->flush();
                            $logger->info('Category updated successfully with ID: ' . $categoryToEdit->getId());
                            $this->addFlash('success', 'Category updated successfully!');
                            return $this->redirectToRoute('admin_category_index');
                        } catch (\Exception $e) {
                            $logger->error('Error updating category: ' . $e->getMessage());
                            $this->addFlash('error', 'An error occurred while updating the category.');
                        }
                    }
                } else {
                    $this->addFlash('error', 'Category not found.');
                    return $this->redirectToRoute('admin_category_index');
                }
            }

            // Handle new category form
            if (!$editForm || !$editForm->isSubmitted()) {
                $newCategory = new Category();
                $newForm = $this->createForm(CategoryFormType::class, $newCategory);
                $newForm->handleRequest($request);
                if ($newForm->isSubmitted() && $newForm->isValid()) {
                    try {
                        $logger->info('Creating new category: ' . $newCategory->getName());
                        $this->entityManager->persist($newCategory);
                        $this->entityManager->flush();
                        $logger->info('Category created successfully with ID: ' . $newCategory->getId());
                        $this->addFlash('success', 'Category created successfully!');
                        return $this->redirectToRoute('admin_category_index');
                    } catch (\Exception $e) {
                        $logger->error('Error creating category: ' . $e->getMessage());
                        $this->addFlash('error', 'An error occurred while creating the category.');
                    }
                }
            }

            $logger->info('Fetched categories: ' . count($categories));

            return $this->render('category/index.html.twig', [
                'categories' => $categories,
                'newForm' => $newForm ? $newForm->createView() : null,
                'editForm' => $editForm ? $editForm->createView() : null,
                'editCategoryId' => $editCategoryId,
            ]);
        } catch (\Exception $e) {
            $logger->error('Error in CategoryController::index: ' . $e->getMessage() . ' | Trace: ' . $e->getTraceAsString());
            $this->addFlash('error', 'An error occurred while fetching categories.');
            return $this->render('category/index.html.twig', [
                'categories' => [],
                'newForm' => $this->createForm(CategoryFormType::class, new Category())->createView(),
                'editForm' => null,
                'editCategoryId' => null,
            ]);
        }
    }

    #[Route('/{id}/edit', name: 'admin_category_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(Request $request, Category $category): Response
    {
        // Redirect to index with edit parameter
        return $this->redirectToRoute('admin_category_index', ['edit' => $category->getId()]);
    }

    #[Route('/{id}', name: 'admin_category_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Request $request, Category $category, LoggerInterface $logger): Response
    {
        if ($this->isCsrfTokenValid('delete' . $category->getId(), $request->request->get('_token'))) {
            try {
                $logger->info('Deleting category: ' . $category->getName());
                $this->entityManager->remove($category);
                $this->entityManager->flush();
                $logger->info('Category deleted successfully with ID: ' . $category->getId());
                $this->addFlash('success', 'Category deleted successfully!');
            } catch (\Exception $e) {
                $logger->error('Error deleting category: ' . $e->getMessage());
                $this->addFlash('error', 'An error occurred while deleting the category.');
            }
        } else {
            $this->addFlash('error', 'Invalid CSRF token.');
        }

        return $this->redirectToRoute('admin_category_index');
    }
}