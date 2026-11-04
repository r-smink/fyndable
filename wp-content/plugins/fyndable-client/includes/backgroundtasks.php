<?php

namespace SSEOAIClient;

/**
 * Background Tasks
 *
 * Generic task queue that ensures AI REST calls survive browser navigation.
 * When a user starts an AI action and navigates away, the PHP process
 * continues (via ignore_user_abort) and stores the result. On return,
 * the frontend polls for completed tasks and shows a notification.
 *
 * Storage: wp_option `sseo_ai_background_tasks` (JSON array).
 * Cleanup: tasks older than 24 hours are pruned via WP-Cron.
 */
class BackgroundTasks
{
    private const OPTION_KEY = 'sseo_ai_background_tasks';
    private const MAX_AGE_SECONDS = 86400; // 24 hours

    public function register(): void
    {
        add_action('rest_api_init', [$this, 'registerRestRoutes']);

        // Cleanup cron
        add_action('sseo_ai_cleanup_background_tasks', [$this, 'cleanup']);
        if (!wp_next_scheduled('sseo_ai_cleanup_background_tasks')) {
            wp_schedule_event(time(), 'hourly', 'sseo_ai_cleanup_background_tasks');
        }
    }

    public function registerRestRoutes(): void
    {
        register_rest_route('sseo-ai/v1', '/tasks/pending', [
            'methods'  => 'GET',
            'callback' => [$this, 'restGetPending'],
            'permission_callback' => fn() => current_user_can('edit_posts'),
        ]);

        register_rest_route('sseo-ai/v1', '/tasks/(?P<task_id>[a-f0-9]+)', [
            'methods'  => 'GET',
            'callback' => [$this, 'restGetTask'],
            'permission_callback' => fn() => current_user_can('edit_posts'),
        ]);

        register_rest_route('sseo-ai/v1', '/tasks/(?P<task_id>[a-f0-9]+)/dismiss', [
            'methods'  => 'POST',
            'callback' => [$this, 'restDismissTask'],
            'permission_callback' => fn() => current_user_can('edit_posts'),
        ]);
    }

    /**
     * Create a new background task. Returns the task ID.
     *
     * @param string $type   Task type (e.g. 'write_article', 'editor_action')
     * @param string $label  Human-readable label shown in notification
     * @param array  $params Original request params (stored for reference)
     */
    public function create(string $type, string $label, array $params = []): string
    {
        $taskId = bin2hex(random_bytes(8));
        $userId = get_current_user_id();

        $task = [
            'id'           => $taskId,
            'user_id'      => $userId,
            'type'         => $type,
            'label'        => $label,
            'status'       => 'processing',
            'params'       => $params,
            'result'       => null,
            'error'        => null,
            'created_at'   => time(),
            'completed_at' => null,
            'dismissed'    => false,
        ];

        $tasks = $this->getTasks();
        $tasks[] = $task;
        $this->saveTasks($tasks);

        return $taskId;
    }

    /**
     * Mark a task as completed with its result data.
     */
    public function complete(string $taskId, array $result): void
    {
        $tasks = $this->getTasks();
        foreach ($tasks as &$task) {
            if ($task['id'] === $taskId) {
                $task['status'] = 'completed';
                $task['result'] = $result;
                $task['completed_at'] = time();
                break;
            }
        }
        $this->saveTasks($tasks);
    }

    /**
     * Mark a task as failed with an error message.
     */
    public function fail(string $taskId, string $error): void
    {
        $tasks = $this->getTasks();
        foreach ($tasks as &$task) {
            if ($task['id'] === $taskId) {
                $task['status'] = 'failed';
                $task['error'] = $error;
                $task['completed_at'] = time();
                break;
            }
        }
        $this->saveTasks($tasks);
    }

    /**
     * Wrap a REST handler to run as a background task.
     *
     * Ensures the PHP process continues even if the client disconnects.
     * The result is stored and returned normally if the client is still connected.
     *
     * @param string   $type     Task type identifier
     * @param string   $label    Human-readable label
     * @param array    $params   Request params for reference
     * @param callable $callback The actual work: fn() => array|\WP_Error
     * @return array|\WP_Error   The result (also stored in background task)
     */
    public function wrap(string $type, string $label, array $params, callable $callback): array|\WP_Error
    {
        $taskId = $this->create($type, $label, $params);

        // Ensure PHP continues processing even if the browser navigates away
        ignore_user_abort(true);
        if (function_exists('set_time_limit')) {
            @set_time_limit(600);
        }

        // Close the session early so other requests aren't blocked
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        try {
            $result = $callback();

            if (is_wp_error($result)) {
                $this->fail($taskId, $result->get_error_message());
                return $result;
            }

            // Store the result for later retrieval
            $result['_task_id'] = $taskId;
            $this->complete($taskId, $result);

            return $result;
        } catch (\Throwable $e) {
            $this->fail($taskId, $e->getMessage());
            return new \WP_Error('background_task_failed', $e->getMessage());
        }
    }

    /**
     * REST: Get all pending/completed (not dismissed) tasks for the current user.
     */
    public function restGetPending(\WP_REST_Request $request): array
    {
        $userId = get_current_user_id();
        $tasks = $this->getTasks();

        $pending = [];
        foreach ($tasks as $task) {
            if ((int) $task['user_id'] !== $userId) continue;
            if (!empty($task['dismissed'])) continue;
            // Only return completed/failed tasks that haven't been dismissed
            // (processing tasks are still running)
            $pending[] = [
                'id'           => $task['id'],
                'type'         => $task['type'],
                'label'        => $task['label'],
                'status'       => $task['status'],
                'result'       => $task['status'] === 'completed' ? $task['result'] : null,
                'error'        => $task['error'],
                'created_at'   => $task['created_at'],
                'completed_at' => $task['completed_at'],
            ];
        }

        return ['tasks' => $pending];
    }

    /**
     * REST: Get a specific task by ID.
     */
    public function restGetTask(\WP_REST_Request $request): array|\WP_Error
    {
        $taskId = $request->get_param('task_id');
        $userId = get_current_user_id();
        $tasks = $this->getTasks();

        foreach ($tasks as $task) {
            if ($task['id'] === $taskId && (int) $task['user_id'] === $userId) {
                return [
                    'id'           => $task['id'],
                    'type'         => $task['type'],
                    'label'        => $task['label'],
                    'status'       => $task['status'],
                    'result'       => $task['result'],
                    'error'        => $task['error'],
                    'created_at'   => $task['created_at'],
                    'completed_at' => $task['completed_at'],
                ];
            }
        }

        return new \WP_Error('not_found', __('Task not found', 'ai-seo-client'), ['status' => 404]);
    }

    /**
     * REST: Dismiss a completed task (removes it from the notification list).
     */
    public function restDismissTask(\WP_REST_Request $request): array
    {
        $taskId = $request->get_param('task_id');
        $userId = get_current_user_id();
        $tasks = $this->getTasks();

        foreach ($tasks as &$task) {
            if ($task['id'] === $taskId && (int) $task['user_id'] === $userId) {
                $task['dismissed'] = true;
                break;
            }
        }

        $this->saveTasks($tasks);
        return ['success' => true];
    }

    /**
     * Cleanup old tasks (called via WP-Cron).
     */
    public function cleanup(): void
    {
        $tasks = $this->getTasks();
        $cutoff = time() - self::MAX_AGE_SECONDS;

        $tasks = array_values(array_filter($tasks, function ($task) use ($cutoff) {
            return $task['created_at'] > $cutoff;
        }));

        $this->saveTasks($tasks);
    }

    private function getTasks(): array
    {
        return get_option(self::OPTION_KEY, []);
    }

    private function saveTasks(array $tasks): void
    {
        update_option(self::OPTION_KEY, $tasks, false);
    }
}
