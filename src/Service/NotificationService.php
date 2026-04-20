<?php
// src/Service/NotificationService.php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class NotificationService
{
    private const SESSION_KEY = 'budget_notifications';
    
    public function __construct(
        private HttpClientInterface $client,
        private string $ntfyUrl,
        private string $ntfyTopic,
        private RequestStack $requestStack
    ) {}

    /**
     * Send a budget alert notification via ntfy.sh and store locally.
     */
    public function sendBudgetAlert(string $libelle, float $pct, float $restant, string $devise): bool
    {
        $isOver  = $restant < 0;
        $emoji   = $isOver  ? '🚨' : ($pct >= 90 ? '⚠️' : '💰');
        $status  = $isOver  ? 'DÉPASSÉ'
                 : ($pct >= 90 ? 'Critique' : 'Alerte');

        $title   = "$emoji Budget $status — $libelle";

        if ($isOver) {
            $body = "Le budget \"$libelle\" est DÉPASSÉ de "
                  . number_format(abs($restant), 2, ',', ' ')
                  . " $devise ($pct% consommé). Revoyez vos dépenses immédiatement.";
        } else {
            $body = "Le budget \"$libelle\" a atteint $pct% de consommation. "
                  . "Il vous reste " . number_format($restant, 2, ',', ' ')
                  . " $devise. Pensez à contrôler vos dépenses.";
        }

        $priority = $isOver ? 'urgent' : ($pct >= 90 ? 'high' : 'default');
        $tags = $isOver ? 'rotating_light,money_with_wings' : 'warning,moneybag';

        // Store notification locally in session
        $this->storeNotification([
            'id' => uniqid(),
            'title' => $title,
            'body' => $body,
            'priority' => $priority,
            'tags' => $tags,
            'date' => new \DateTime(),
            'is_read' => false,
            'budget_name' => $libelle,
            'percentage' => $pct,
            'is_over' => $isOver
        ]);

        // Send to ntfy
        try {
            $response = $this->client->request('POST', rtrim($this->ntfyUrl, '/') . '/' . $this->ntfyTopic, [
                'headers' => [
                    'Title'    => $title,
                    'Priority' => $priority,
                    'Tags'     => $tags,
                    'Content-Type' => 'text/plain; charset=utf-8',
                ],
                'body' => $body,
            ]);

            return $response->getStatusCode() === 200;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Store a notification in session.
     */
    private function storeNotification(array $notification): void
    {
        $session = $this->requestStack->getSession();
        $notifications = $session->get(self::SESSION_KEY, []);
        
        // Add to beginning of array (newest first)
        array_unshift($notifications, $notification);
        
        // Keep only last 50 notifications
        $notifications = array_slice($notifications, 0, 50);
        
        $session->set(self::SESSION_KEY, $notifications);
    }

    /**
     * Get all notifications for current session.
     */
    public function getNotifications(): array
    {
        $session = $this->requestStack->getSession();
        return $session->get(self::SESSION_KEY, []);
    }

    /**
     * Get unread notifications count.
     */
    public function getUnreadCount(): int
    {
        $notifications = $this->getNotifications();
        return count(array_filter($notifications, fn($n) => !$n['is_read']));
    }

    /**
     * Mark a notification as read.
     */
    public function markAsRead(string $id): void
    {
        $session = $this->requestStack->getSession();
        $notifications = $session->get(self::SESSION_KEY, []);
        
        foreach ($notifications as &$notification) {
            if ($notification['id'] === $id) {
                $notification['is_read'] = true;
                break;
            }
        }
        
        $session->set(self::SESSION_KEY, $notifications);
    }

    /**
     * Mark all notifications as read.
     */
    public function markAllAsRead(): void
    {
        $session = $this->requestStack->getSession();
        $notifications = $session->get(self::SESSION_KEY, []);
        
        foreach ($notifications as &$notification) {
            $notification['is_read'] = true;
        }
        
        $session->set(self::SESSION_KEY, $notifications);
    }

    /**
     * Clear all notifications.
     */
    public function clearAllNotifications(): void
    {
        $session = $this->requestStack->getSession();
        $session->set(self::SESSION_KEY, []);
    }

    /**
     * Delete a specific notification.
     */
    public function deleteNotification(string $id): void
    {
        $session = $this->requestStack->getSession();
        $notifications = $session->get(self::SESSION_KEY, []);
        
        $notifications = array_filter($notifications, fn($n) => $n['id'] !== $id);
        
        $session->set(self::SESSION_KEY, array_values($notifications));
    }
}