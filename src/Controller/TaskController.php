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
use Psr\Log\LoggerInterface;

#[Route('/api', name: 'app_task')]
final class TaskController extends AbstractController
{
    private $serializer;
    private $validator;
    private $logger;

    public function __construct(SerializerInterface $serializer, ValidatorInterface $validator, LoggerInterface $logger)
    {
        $this->serializer = $serializer;
        $this->validator = $validator;
        $this->logger    = $logger;
    }

    #[Route('', name: 'welcome')]
    public function index(): JsonResponse
    {
        return $this->json([
            'message' => 'Welcome to task manager api!'
        ]);
    }

    #[Route('/tasks', name: 'task_all', methods: ['GET'])]
    public function all_tasks(Request $request, TaskRepository $taskRepository): JsonResponse
    {
        $status = $request->query->get('status');
        $page   = (int) $request->query->get('page', 1);
        $limit   = (int) $request->query->get('limit', 10);
        
        if ($page < 1) {
            $page = 1;
        }
        if ($limit < 1 || $limit > 100) {
            $limit = 10;
        }

        $tasks = $taskRepository->findWithFilters($status, $page, $limit);
        $total = $this->getTotalCount($taskRepository, $status);
        return $this->json([
            'success' => true,
            'data' => $tasks,
            'pagination' => [
                'page' => $page,
                'record_per_page' => $limit,
                'total_tasks' => $total
            ],

        ], Response::HTTP_OK, [], ['groups' => ['task_read']]);
    }

    private function getTotalCount(TaskRepository $taskRepository, ?string $status): int
    {
        $qb = $taskRepository->createQueryBuilder('t')
            ->select('COUNT(t.id)');

        if ($status !== null && $status !== '') {
            $qb->where('t.status = :status')
               ->setParameter('status', $status);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
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

                $errorMessages = [];
                foreach ($errors as $error) {
                    $errorMessages[] = $error->getPropertyPath() . ': ' . $error->getMessage();
                }                
                $this->logger->warning('Validation failed for task creation', [
                    'errors' => $errorMessages,
                    'input_data' => $request->getContent()
                ]);
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
            $this->logger->warning("There is an error creating task", [
                "error" => $e->getMessage(),
                "input_data" => $request->getContent()
            ]);
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
                $errorMessages = [];
                foreach ($errors as $error) {
                    $errorMessages[] = $error->getPropertyPath() . ': ' . $error->getMessage();
                }                
                $this->logger->warning('Validation failed for task update', [
                    'errors' => $errorMessages,
                    'input_data' => $request->getContent()
                ]);                
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
            $this->logger->warning('There is an error updating task', [
                "error" => $e->getMessage(),
                'input_data' => $request->getContent()
            ]);             
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
            $this->logger->warning('There is an error deleting task', [
                "error" => $e->getMessage(),
                'data to delete' => $task
            ]);              
            return $this->json([
                'success' => false,
                'error' => 'Failed to delete task: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }    

}
