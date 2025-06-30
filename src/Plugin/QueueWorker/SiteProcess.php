<?php

/**
 * @file
 * Contains \Drupal\example_queue\Plugin\QueueWorker\ExampleQueueWorker.
 */

namespace Drupal\site_manager\Plugin\QueueWorker;

use Drupal\Core\Queue\QueueWorkerBase;

/**
 * Processes tasks for module.
 *
 * @QueueWorker(
 *   id = "site_process",
 *   title = @Translation("Site Build Queue worker"),
 *   cron = {"time" = 60}
 * )
 */

class SiteProcess extends QueueWorkerBase
{
    /**
     * {@inheritdoc}
     */
    public function processItem($item) {
        $site = new \Drupal\site_manager\SiteManager($item);
        $site->process();
        $item->moderation_state->value = "published";
        $item->save();
       // $site->duplicate('tbf_new','global_database');
    }
}