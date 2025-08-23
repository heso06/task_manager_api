<?php

namespace App\Controller;

use App\Entity\Task;
use App\Repository\TaskRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\TextUI\XmlConfiguration\Validator as PHPUnitValidator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api', name: 'app_task')]
final class TaskController extends AbstractController
{
    private $serializer;
    private $validator;


    public function __construct(SerializerInterface $serializer, ValidatorInterface $validator)
    {
        $this->serializer = $serializer;
        $this->validator = $validator;
    }

    #[Route('', name: 'welcome')]
    public function index(): JsonResponse
    {
        return $this->json([
            'message' => 'Welcome to task manager api!'
        ]);
    }

    #[Route('/tasks', name: 'task_all', methods: ['GET'])]
    public function all_tasks(TaskRepository $taskRepository): JsonResponse
    {
        $tasks = $taskRepository->findAll();
        
        return $this->json([
            'success' => true,
            'data' => $tasks,
            'total' => count($tasks)
        ], Response::HTTP_OK, [], ['groups' => ['task_read']]);
    }

    #[Route('/tasks/{id}', name: 'task_show', methods: ['GET'])]
    public function show(Task $task): JsonResponse
    {
        return $this->json([
            'success' => true,
            'data' => $task
        ], Response::HTTP_OK, [], ['groups' => ['task_read']]);
    }

    #[Route('/tasks', name: 'task_create', methods: ['POST'])]
    public function create(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        try {
            $task = $this->serializer->deserialize($request->getContent(), Task::class, 'json');
            
            $errors = $this->validator->validate($task);
            if (count($errors) > 0) {
                return $this->json([
                    'success' => false,
                    'errors' => (string) $errors
                ], Response::HTTP_BAD_REQUEST);
            }

            $task->setCreatedAt(new \DateTime());
            $task->setUpdatedAt(new \DateTime());

            if ($task->getStatus() === null) $task->setStatus('created');
            $entityManager->persist($task);
            $entityManager->flush();

            return $this->json([
                'success' => true,
                'data' => $task,
                'message' => 'Task created successfully'
            ], Response::HTTP_CREATED, [], ['groups' => ['task_read']]);

        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => 'Failed to create task: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/tasks/{id}', name: 'task_update', methods: ['PUT'])]
    public function update(Request $request, Task $task, EntityManagerInterface $entityManager): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            
            if (isset($data['title'])) {
                $task->setTitle($data['title']);
            }
            if (isset($data['description'])) {
                $task->setDescription($data['description']);
            }
            if (isset($data['status'])) {
                $task->setStatus($data['status']);
            }
            
            $task->setUpdatedAt(new \DateTime());

            $errors = $this->validator->validate($task);
            if (count($errors) > 0) {
                return $this->json([
                    'success' => false,
                    'errors' => (string) $errors
                ], Response::HTTP_BAD_REQUEST);
            }

            $entityManager->flush();

            return $this->json([
                'success' => true,
                'data' => $task,
                'message' => 'Task updated successfully'
            ], Response::HTTP_OK, [], ['groups' => ['task_read']]);

        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => 'Failed to update task: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/tasks/{id}', name: 'task_delete', methods: ['DELETE'])]
    public function delete(Task $task, EntityManagerInterface $entityManager): JsonResponse
    {
        try {
            $entityManager->remove($task);
            $entityManager->flush();

            return $this->json([
                'success' => true,
                'message' => 'Task deleted successfully'
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => 'Failed to delete task: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }    

}
