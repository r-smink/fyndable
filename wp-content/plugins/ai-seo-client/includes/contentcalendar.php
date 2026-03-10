<?php

namespace AISEOClient;

/**
 * Content Calendar & Workflow Manager
 * 
 * Provides content planning and workflow features:
 * - Visual content calendar
 * - Team assignment & approval workflow
 * - Publishing schedule optimization
 * - Content gap calendar
 * - Keyword opportunity timeline
 * - Competitor publishing tracking
 * - Slack/email notifications for approvals
 */
class ContentCalendar
{
    private Settings $settings;
    private LLMClient $llm;
    
    public function __construct(Settings $settings, LLMClient $llm)
    {
        $this->settings = $settings;
        $this->llm = $llm;
    }
    
    public function register(): void
    {
        add_action('admin_menu', [$this, 'addMenu']);
        add_action('admin_init', [$this, 'registerSettings']);
        add_action('rest_api_init', [$this, 'registerRestRoutes']);
        add_action('transition_post_status', [$this, 'handleStatusChange'], 10, 3);
        add_action('wp_ajax_sseo_ai_assign_content', [$this, 'ajaxAssignContent']);
        add_action('wp_ajax_sseo_ai_approve_content', [$this, 'ajaxApproveContent']);
        
        // Add workflow meta box
        add_action('add_meta_boxes', [$this, 'addWorkflowMetaBox']);
        add_action('save_post', [$this, 'saveWorkflowMetaBox'], 10, 2);
    }
    
    public function addMenu(): void
    {
        add_submenu_page(
            'ai-seo-client',
            __('Content Calendar', 'ai-seo-client'),
            __('Content Calendar', 'ai-seo-client'),
            'edit_posts',
            'ai-seo-content-calendar',
            [$this, 'renderCalendar']
        );
    }
    
    /**
     * Register settings
     */
    public function registerSettings(): void
    {
        register_setting('sseo_ai_calendar', 'sseo_ai_workflow_enabled', ['default' => true]);
        register_setting('sseo_ai_calendar', 'sseo_ai_approval_required', ['default' => false]);
        register_setting('sseo_ai_calendar', 'sseo_ai_notify_slack', ['default' => false]);
        register_setting('sseo_ai_calendar', 'sseo_ai_notify_email', ['default' => true]);
        register_setting('sseo_ai_calendar', 'sseo_ai_publishing_frequency', ['default' => 'weekly']);
    }
    
    /**
     * Render content calendar
     */
    public function renderCalendar(): void
    {
        $currentMonth = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('n');
        $currentYear = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
        
        $calendarData = $this->getCalendarData($currentMonth, $currentYear);
        $contentGaps = $this->identifyContentGaps();
        $keywordOpportunities = $this->getKeywordOpportunities();
        
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Content Calendar & Workflow', 'ai-seo-client'); ?></h1>
            
            <!-- Calendar Navigation -->
            <div class="card" style="margin-bottom: 20px;">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <a href="<?php echo admin_url('admin.php?page=ai-seo-content-calendar&month=' . ($currentMonth - 1) . '&year=' . $currentYear); ?>" 
                       class="button">
                        ← <?php esc_html_e('Previous', 'ai-seo-client'); ?>
                    </a>
                    
                    <h2 style="margin: 0;">
                        <?php echo date('F Y', mktime(0, 0, 0, $currentMonth, 1, $currentYear)); ?>
                    </h2>
                    
                    <a href="<?php echo admin_url('admin.php?page=ai-seo-content-calendar&month=' . ($currentMonth + 1) . '&year=' . $currentYear); ?>" 
                       class="button">
                        <?php esc_html_e('Next', 'ai-seo-client'); ?> →
                    </a>
                </div>
            </div>
            
            <!-- Visual Calendar -->
            <div class="card" style="margin-bottom: 20px;">
                <h2><?php esc_html_e('Publishing Calendar', 'ai-seo-client'); ?></h2>
                
                <style>
                .sseo-calendar { width: 100%; border-collapse: collapse; }
                .sseo-calendar th { background: #2271b1; color: white; padding: 10px; text-align: center; }
                .sseo-calendar td { border: 1px solid #ddd; padding: 10px; vertical-align: top; height: 120px; }
                .sseo-calendar .day-number { font-weight: bold; margin-bottom: 5px; }
                .sseo-calendar .content-item { background: #f0f6fc; padding: 5px; margin: 3px 0; border-radius: 3px; font-size: 12px; cursor: pointer; }
                .sseo-calendar .content-item.draft { background: #fff3cd; }
                .sseo-calendar .content-item.pending { background: #cfe2ff; }
                .sseo-calendar .content-item.scheduled { background: #d1e7dd; }
                .sseo-calendar .today { background: #f0f6fc; }
                .sseo-calendar .content-gap { background: #f8d7da; }
                </style>
                
                <table class="sseo-calendar">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Mon', 'ai-seo-client'); ?></th>
                            <th><?php esc_html_e('Tue', 'ai-seo-client'); ?></th>
                            <th><?php esc_html_e('Wed', 'ai-seo-client'); ?></th>
                            <th><?php esc_html_e('Thu', 'ai-seo-client'); ?></th>
                            <th><?php esc_html_e('Fri', 'ai-seo-client'); ?></th>
                            <th><?php esc_html_e('Sat', 'ai-seo-client'); ?></th>
                            <th><?php esc_html_e('Sun', 'ai-seo-client'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $currentMonth, $currentYear);
                        $firstDay = date('N', mktime(0, 0, 0, $currentMonth, 1, $currentYear));
                        $today = date('Y-m-d');
                        
                        $day = 1;
                        for ($week = 0; $week < 6; $week++):
                            if ($day > $daysInMonth) break;
                        ?>
                        <tr>
                            <?php for ($dayOfWeek = 1; $dayOfWeek <= 7; $dayOfWeek++): ?>
                            <td class="<?php echo ($week === 0 && $dayOfWeek < $firstDay) || $day > $daysInMonth ? '' : ''; ?>
                                       <?php 
                                       $currentDate = sprintf('%04d-%02d-%02d', $currentYear, $currentMonth, $day);
                                       echo $currentDate === $today ? 'today' : ''; 
                                       ?>">
                                <?php if (($week === 0 && $dayOfWeek >= $firstDay) || ($week > 0 && $day <= $daysInMonth)): ?>
                                    <div class="day-number"><?php echo $day; ?></div>
                                    
                                    <?php
                                    $dateKey = sprintf('%04d-%02d-%02d', $currentYear, $currentMonth, $day);
                                    if (isset($calendarData[$dateKey])):
                                        foreach ($calendarData[$dateKey] as $item):
                                    ?>
                                    <div class="content-item <?php echo esc_attr($item['status']); ?>" 
                                         onclick="sseoEditContent(<?php echo $item['id']; ?>)"
                                         title="<?php echo esc_attr($item['title']); ?>">
                                        <?php echo esc_html(mb_substr($item['title'], 0, 30)); ?>
                                        <?php if (strlen($item['title']) > 30) echo '...'; ?>
                                    </div>
                                    <?php 
                                        endforeach;
                                    endif;
                                    
                                    // Show content gap indicator
                                    if (in_array($dateKey, $contentGaps)):
                                    ?>
                                    <div style="color: #d63638; font-size: 11px; margin-top: 5px;">
                                        ⚠ <?php esc_html_e('Gap', 'ai-seo-client'); ?>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php $day++; ?>
                                <?php endif; ?>
                            </td>
                            <?php endfor; ?>
                        </tr>
                        <?php endfor; ?>
                    </tbody>
                </table>
                
                <div style="margin-top: 15px;">
                    <button type="button" class="button button-primary" onclick="sseoScheduleContent()">
                        <?php esc_html_e('Schedule New Content', 'ai-seo-client'); ?>
                    </button>
                    <button type="button" class="button" onclick="sseoOptimizeSchedule()">
                        <?php esc_html_e('Optimize Publishing Schedule', 'ai-seo-client'); ?>
                    </button>
                </div>
            </div>
            
            <!-- Content Gap Analysis -->
            <div class="card" style="margin-bottom: 20px;">
                <h2><?php esc_html_e('Content Gap Calendar', 'ai-seo-client'); ?></h2>
                
                <?php if (!empty($contentGaps)): ?>
                <p><?php esc_html_e('Identified gaps in your content calendar:', 'ai-seo-client'); ?></p>
                <ul>
                    <?php foreach (array_slice($contentGaps, 0, 10) as $gap): ?>
                    <li>
                        <strong><?php echo esc_html(date('F j, Y', strtotime($gap))); ?></strong> -
                        <?php esc_html_e('No content scheduled', 'ai-seo-client'); ?>
                        <button type="button" class="button button-small" 
                                onclick="sseoFillGap('<?php echo esc_js($gap); ?>')">
                            <?php esc_html_e('Fill Gap', 'ai-seo-client'); ?>
                        </button>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php else: ?>
                <p style="color: #00a32a;">✓ <?php esc_html_e('No content gaps detected!', 'ai-seo-client'); ?></p>
                <?php endif; ?>
            </div>
            
            <!-- Keyword Opportunity Timeline -->
            <div class="card" style="margin-bottom: 20px;">
                <h2><?php esc_html_e('Keyword Opportunity Timeline', 'ai-seo-client'); ?></h2>
                
                <?php if (!empty($keywordOpportunities)): ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Keyword', 'ai-seo-client'); ?></th>
                            <th><?php esc_html_e('Opportunity Type', 'ai-seo-client'); ?></th>
                            <th><?php esc_html_e('Suggested Date', 'ai-seo-client'); ?></th>
                            <th><?php esc_html_e('Priority', 'ai-seo-client'); ?></th>
                            <th><?php esc_html_e('Actions', 'ai-seo-client'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($keywordOpportunities as $opp): ?>
                        <tr>
                            <td><strong><?php echo esc_html($opp['keyword']); ?></strong></td>
                            <td><?php echo esc_html($opp['type']); ?></td>
                            <td><?php echo esc_html(date('M j, Y', strtotime($opp['suggested_date']))); ?></td>
                            <td>
                                <span style="color: <?php echo $opp['priority'] === 'high' ? '#d63638' : '#dba617'; ?>;">
                                    <?php echo esc_html(ucfirst($opp['priority'])); ?>
                                </span>
                            </td>
                            <td>
                                <button type="button" class="button button-small" 
                                        onclick="sseoCreateFromOpportunity('<?php echo esc_js($opp['keyword']); ?>', '<?php echo esc_js($opp['suggested_date']); ?>')">
                                    <?php esc_html_e('Create Content', 'ai-seo-client'); ?>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <p><?php esc_html_e('No keyword opportunities identified yet.', 'ai-seo-client'); ?></p>
                <?php endif; ?>
            </div>
            
            <!-- Workflow Status -->
            <div class="card">
                <h2><?php esc_html_e('Content Workflow Status', 'ai-seo-client'); ?></h2>
                
                <?php
                $workflowStats = $this->getWorkflowStats();
                ?>
                
                <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 20px;">
                    <div style="text-align: center; padding: 20px; background: #fff3cd; border-radius: 4px;">
                        <div style="font-size: 36px; font-weight: bold;"><?php echo esc_html($workflowStats['draft']); ?></div>
                        <div><?php esc_html_e('Drafts', 'ai-seo-client'); ?></div>
                    </div>
                    <div style="text-align: center; padding: 20px; background: #cfe2ff; border-radius: 4px;">
                        <div style="font-size: 36px; font-weight: bold;"><?php echo esc_html($workflowStats['pending']); ?></div>
                        <div><?php esc_html_e('Pending Review', 'ai-seo-client'); ?></div>
                    </div>
                    <div style="text-align: center; padding: 20px; background: #d1e7dd; border-radius: 4px;">
                        <div style="font-size: 36px; font-weight: bold;"><?php echo esc_html($workflowStats['scheduled']); ?></div>
                        <div><?php esc_html_e('Scheduled', 'ai-seo-client'); ?></div>
                    </div>
                    <div style="text-align: center; padding: 20px; background: #d1ecf1; border-radius: 4px;">
                        <div style="font-size: 36px; font-weight: bold;"><?php echo esc_html($workflowStats['published']); ?></div>
                        <div><?php esc_html_e('Published (30d)', 'ai-seo-client'); ?></div>
                    </div>
                </div>
                
                <h3><?php esc_html_e('Pending Approvals', 'ai-seo-client'); ?></h3>
                <?php
                $pendingApprovals = $this->getPendingApprovals();
                if (!empty($pendingApprovals)):
                ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Content', 'ai-seo-client'); ?></th>
                            <th><?php esc_html_e('Author', 'ai-seo-client'); ?></th>
                            <th><?php esc_html_e('Assigned To', 'ai-seo-client'); ?></th>
                            <th><?php esc_html_e('Submitted', 'ai-seo-client'); ?></th>
                            <th><?php esc_html_e('Actions', 'ai-seo-client'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pendingApprovals as $approval): ?>
                        <tr>
                            <td>
                                <a href="<?php echo get_edit_post_link($approval['post_id']); ?>">
                                    <?php echo esc_html($approval['title']); ?>
                                </a>
                            </td>
                            <td><?php echo esc_html($approval['author']); ?></td>
                            <td><?php echo esc_html($approval['assigned_to']); ?></td>
                            <td><?php echo esc_html(human_time_diff(strtotime($approval['submitted']))); ?> ago</td>
                            <td>
                                <button type="button" class="button button-small button-primary" 
                                        onclick="sseoApproveContent(<?php echo $approval['post_id']; ?>)">
                                    <?php esc_html_e('Approve', 'ai-seo-client'); ?>
                                </button>
                                <button type="button" class="button button-small" 
                                        onclick="sseoRejectContent(<?php echo $approval['post_id']; ?>)">
                                    <?php esc_html_e('Reject', 'ai-seo-client'); ?>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <p><?php esc_html_e('No pending approvals.', 'ai-seo-client'); ?></p>
                <?php endif; ?>
            </div>
        </div>
        
        <script>
        function sseoEditContent(postId) {
            window.location.href = '<?php echo admin_url('post.php?action=edit&post='); ?>' + postId;
        }
        
        function sseoScheduleContent() {
            window.location.href = '<?php echo admin_url('post-new.php'); ?>';
        }
        
        function sseoOptimizeSchedule() {
            if (!confirm('<?php esc_html_e('Analyze and optimize your publishing schedule?', 'ai-seo-client'); ?>')) {
                return;
            }
            
            jQuery.post(ajaxurl, {
                action: 'sseo_ai_optimize_schedule',
                nonce: '<?php echo wp_create_nonce('sseo_calendar'); ?>'
            }, function(response) {
                if (response.success) {
                    alert(response.data.message);
                    location.reload();
                } else {
                    alert(response.data.message || 'Error optimizing schedule');
                }
            });
        }
        
        function sseoFillGap(date) {
            window.location.href = '<?php echo admin_url('post-new.php?suggested_date='); ?>' + date;
        }
        
        function sseoCreateFromOpportunity(keyword, date) {
            window.location.href = '<?php echo admin_url('post-new.php?keyword='); ?>' + encodeURIComponent(keyword) + '&suggested_date=' + date;
        }
        
        function sseoApproveContent(postId) {
            jQuery.post(ajaxurl, {
                action: 'sseo_ai_approve_content',
                post_id: postId,
                nonce: '<?php echo wp_create_nonce('sseo_calendar'); ?>'
            }, function(response) {
                if (response.success) {
                    alert('<?php esc_html_e('Content approved!', 'ai-seo-client'); ?>');
                    location.reload();
                } else {
                    alert(response.data.message || 'Error approving content');
                }
            });
        }
        
        function sseoRejectContent(postId) {
            const reason = prompt('<?php esc_html_e('Reason for rejection:', 'ai-seo-client'); ?>');
            if (!reason) return;
            
            jQuery.post(ajaxurl, {
                action: 'sseo_ai_reject_content',
                post_id: postId,
                reason: reason,
                nonce: '<?php echo wp_create_nonce('sseo_calendar'); ?>'
            }, function(response) {
                if (response.success) {
                    alert('<?php esc_html_e('Content rejected', 'ai-seo-client'); ?>');
                    location.reload();
                }
            });
        }
        </script>
        <?php
    }
    
    /**
     * Add workflow meta box
     */
    public function addWorkflowMetaBox(): void
    {
        $postTypes = get_post_types(['public' => true], 'names');
        
        foreach ($postTypes as $postType) {
            add_meta_box(
                'sseo-workflow',
                __('Content Workflow', 'ai-seo-client'),
                [$this, 'renderWorkflowMetaBox'],
                $postType,
                'side',
                'high'
            );
        }
    }
    
    /**
     * Render workflow meta box
     */
    public function renderWorkflowMetaBox(\WP_Post $post): void
    {
        $assignedTo = get_post_meta($post->ID, '_sseo_ai_assigned_to', true);
        $approver = get_post_meta($post->ID, '_sseo_ai_approver', true);
        $dueDate = get_post_meta($post->ID, '_sseo_ai_due_date', true);
        $workflowStatus = get_post_meta($post->ID, '_sseo_ai_workflow_status', true) ?: 'draft';
        
        wp_nonce_field('sseo_workflow_save', 'sseo_workflow_nonce');
        ?>
        <div class="sseo-workflow-box">
            <p>
                <label for="assigned_to"><strong><?php esc_html_e('Assigned To:', 'ai-seo-client'); ?></strong></label><br>
                <select id="assigned_to" name="sseo_assigned_to" style="width: 100%;">
                    <option value=""><?php esc_html_e('Unassigned', 'ai-seo-client'); ?></option>
                    <?php
                    $users = get_users(['role__in' => ['administrator', 'editor', 'author']]);
                    foreach ($users as $user):
                    ?>
                    <option value="<?php echo esc_attr($user->ID); ?>" <?php selected($assignedTo, $user->ID); ?>>
                        <?php echo esc_html($user->display_name); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </p>
            
            <p>
                <label for="approver"><strong><?php esc_html_e('Approver:', 'ai-seo-client'); ?></strong></label><br>
                <select id="approver" name="sseo_approver" style="width: 100%;">
                    <option value=""><?php esc_html_e('No approval required', 'ai-seo-client'); ?></option>
                    <?php
                    $approvers = get_users(['role__in' => ['administrator', 'editor']]);
                    foreach ($approvers as $user):
                    ?>
                    <option value="<?php echo esc_attr($user->ID); ?>" <?php selected($approver, $user->ID); ?>>
                        <?php echo esc_html($user->display_name); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </p>
            
            <p>
                <label for="due_date"><strong><?php esc_html_e('Due Date:', 'ai-seo-client'); ?></strong></label><br>
                <input type="date" id="due_date" name="sseo_due_date" 
                       value="<?php echo esc_attr($dueDate); ?>" style="width: 100%;">
            </p>
            
            <p>
                <label for="workflow_status"><strong><?php esc_html_e('Workflow Status:', 'ai-seo-client'); ?></strong></label><br>
                <select id="workflow_status" name="sseo_workflow_status" style="width: 100%;">
                    <option value="draft" <?php selected($workflowStatus, 'draft'); ?>><?php esc_html_e('Draft', 'ai-seo-client'); ?></option>
                    <option value="in_progress" <?php selected($workflowStatus, 'in_progress'); ?>><?php esc_html_e('In Progress', 'ai-seo-client'); ?></option>
                    <option value="pending_review" <?php selected($workflowStatus, 'pending_review'); ?>><?php esc_html_e('Pending Review', 'ai-seo-client'); ?></option>
                    <option value="approved" <?php selected($workflowStatus, 'approved'); ?>><?php esc_html_e('Approved', 'ai-seo-client'); ?></option>
                    <option value="rejected" <?php selected($workflowStatus, 'rejected'); ?>><?php esc_html_e('Rejected', 'ai-seo-client'); ?></option>
                </select>
            </p>
            
            <?php if ($workflowStatus === 'pending_review' && current_user_can('edit_others_posts')): ?>
            <p>
                <button type="button" class="button button-primary" style="width: 100%;" 
                        onclick="sseoQuickApprove(<?php echo $post->ID; ?>)">
                    <?php esc_html_e('Approve & Publish', 'ai-seo-client'); ?>
                </button>
            </p>
            <?php endif; ?>
        </div>
        
        <script>
        function sseoQuickApprove(postId) {
            if (!confirm('<?php esc_html_e('Approve and publish this content?', 'ai-seo-client'); ?>')) {
                return;
            }
            
            jQuery.post(ajaxurl, {
                action: 'sseo_ai_approve_content',
                post_id: postId,
                nonce: '<?php echo wp_create_nonce('sseo_calendar'); ?>'
            }, function(response) {
                if (response.success) {
                    location.reload();
                }
            });
        }
        </script>
        <?php
    }
    
    /**
     * Save workflow meta box
     */
    public function saveWorkflowMetaBox(int $postId, \WP_Post $post): void
    {
        if (!isset($_POST['sseo_workflow_nonce']) || !wp_verify_nonce($_POST['sseo_workflow_nonce'], 'sseo_workflow_save')) {
            return;
        }
        
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        if (!current_user_can('edit_post', $postId)) {
            return;
        }
        
        // Save assigned to
        if (isset($_POST['sseo_assigned_to'])) {
            $oldAssignee = get_post_meta($postId, '_sseo_ai_assigned_to', true);
            $newAssignee = (int)$_POST['sseo_assigned_to'];
            
            update_post_meta($postId, '_sseo_ai_assigned_to', $newAssignee);
            
            // Send notification if assignment changed
            if ($oldAssignee != $newAssignee && $newAssignee > 0) {
                $this->sendAssignmentNotification($postId, $newAssignee);
            }
        }
        
        // Save approver
        if (isset($_POST['sseo_approver'])) {
            update_post_meta($postId, '_sseo_ai_approver', (int)$_POST['sseo_approver']);
        }
        
        // Save due date
        if (isset($_POST['sseo_due_date'])) {
            update_post_meta($postId, '_sseo_ai_due_date', sanitize_text_field($_POST['sseo_due_date']));
        }
        
        // Save workflow status
        if (isset($_POST['sseo_workflow_status'])) {
            $oldStatus = get_post_meta($postId, '_sseo_ai_workflow_status', true);
            $newStatus = sanitize_text_field($_POST['sseo_workflow_status']);
            
            update_post_meta($postId, '_sseo_ai_workflow_status', $newStatus);
            
            // Send notification if status changed to pending review
            if ($oldStatus != 'pending_review' && $newStatus === 'pending_review') {
                $this->sendApprovalNotification($postId);
            }
        }
    }
    
    /**
     * Get calendar data
     */
    private function getCalendarData(int $month, int $year): array
    {
        global $wpdb;
        
        $startDate = sprintf('%04d-%02d-01', $year, $month);
        $endDate = date('Y-m-t', strtotime($startDate));
        
        $posts = $wpdb->get_results($wpdb->prepare("
            SELECT ID, post_title, post_status, post_date
            FROM {$wpdb->posts}
            WHERE post_type IN ('post', 'page')
            AND (
                (post_status = 'publish' AND post_date BETWEEN %s AND %s)
                OR (post_status IN ('draft', 'pending', 'future') AND post_modified BETWEEN %s AND %s)
            )
            ORDER BY post_date ASC
        ", $startDate, $endDate . ' 23:59:59', $startDate, $endDate . ' 23:59:59'));
        
        $calendar = [];
        foreach ($posts as $post) {
            $date = date('Y-m-d', strtotime($post->post_date));
            
            if (!isset($calendar[$date])) {
                $calendar[$date] = [];
            }
            
            $calendar[$date][] = [
                'id' => $post->ID,
                'title' => $post->post_title,
                'status' => $post->post_status,
            ];
        }
        
        return $calendar;
    }
    
    /**
     * Identify content gaps
     */
    private function identifyContentGaps(): array
    {
        $frequency = get_option('sseo_ai_publishing_frequency', 'weekly');
        $gaps = [];
        
        // Calculate expected publishing days
        $today = new \DateTime();
        $endDate = (clone $today)->modify('+30 days');
        
        $expectedDays = [];
        switch ($frequency) {
            case 'daily':
                $interval = 1;
                break;
            case 'weekly':
                $interval = 7;
                break;
            case 'biweekly':
                $interval = 14;
                break;
            default:
                $interval = 7;
        }
        
        $current = clone $today;
        while ($current <= $endDate) {
            $expectedDays[] = $current->format('Y-m-d');
            $current->modify("+{$interval} days");
        }
        
        // Check which days have no content
        global $wpdb;
        foreach ($expectedDays as $day) {
            $hasContent = $wpdb->get_var($wpdb->prepare("
                SELECT COUNT(*)
                FROM {$wpdb->posts}
                WHERE post_type = 'post'
                AND post_status IN ('publish', 'future')
                AND DATE(post_date) = %s
            ", $day));
            
            if (!$hasContent) {
                $gaps[] = $day;
            }
        }
        
        return $gaps;
    }
    
    /**
     * Get keyword opportunities
     */
    private function getKeywordOpportunities(): array
    {
        // This would analyze trending keywords and suggest publishing dates
        // Placeholder implementation
        
        return [
            [
                'keyword' => 'summer seo tips',
                'type' => 'Seasonal',
                'suggested_date' => date('Y-m-d', strtotime('+7 days')),
                'priority' => 'high',
            ],
            [
                'keyword' => 'content marketing trends',
                'type' => 'Trending',
                'suggested_date' => date('Y-m-d', strtotime('+14 days')),
                'priority' => 'medium',
            ],
        ];
    }
    
    /**
     * Get workflow stats
     */
    private function getWorkflowStats(): array
    {
        global $wpdb;
        
        return [
            'draft' => (int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status = 'draft' AND post_type = 'post'"),
            'pending' => (int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status = 'pending' AND post_type = 'post'"),
            'scheduled' => (int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status = 'future' AND post_type = 'post'"),
            'published' => (int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_type = 'post' AND post_date > DATE_SUB(NOW(), INTERVAL 30 DAY)"),
        ];
    }
    
    /**
     * Get pending approvals
     */
    private function getPendingApprovals(): array
    {
        global $wpdb;
        
        $results = $wpdb->get_results("
            SELECT p.ID as post_id, p.post_title as title, p.post_author, p.post_modified as submitted,
                   pm1.meta_value as assigned_to
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm1 ON p.ID = pm1.post_id AND pm1.meta_key = '_sseo_ai_assigned_to'
            LEFT JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = '_sseo_ai_workflow_status'
            WHERE p.post_type = 'post'
            AND pm2.meta_value = 'pending_review'
            ORDER BY p.post_modified DESC
        ");
        
        $approvals = [];
        foreach ($results as $row) {
            $author = get_userdata($row->post_author);
            $assignee = get_userdata($row->assigned_to);
            
            $approvals[] = [
                'post_id' => $row->post_id,
                'title' => $row->title,
                'author' => $author ? $author->display_name : 'Unknown',
                'assigned_to' => $assignee ? $assignee->display_name : 'Unassigned',
                'submitted' => $row->submitted,
            ];
        }
        
        return $approvals;
    }
    
    /**
     * Send assignment notification
     */
    private function sendAssignmentNotification(int $postId, int $userId): void
    {
        $user = get_userdata($userId);
        if (!$user) return;
        
        $post = get_post($postId);
        $notifyEmail = get_option('sseo_ai_notify_email', true);
        $notifySlack = get_option('sseo_ai_notify_slack', false);
        
        $message = sprintf(
            __('You have been assigned to: %s', 'ai-seo-client'),
            $post->post_title
        );
        
        // Email notification
        if ($notifyEmail) {
            wp_mail(
                $user->user_email,
                __('New Content Assignment', 'ai-seo-client'),
                $message . "\n\n" . get_edit_post_link($postId)
            );
        }
        
        // Slack notification
        if ($notifySlack) {
            $externalIntegrations = new ExternalIntegrations($this->settings);
            $externalIntegrations->sendSlackNotification(
                ':memo: ' . $message,
                [[
                    'color' => 'good',
                    'fields' => [
                        ['title' => 'Assigned To', 'value' => $user->display_name, 'short' => true],
                        ['title' => 'Content', 'value' => $post->post_title, 'short' => true],
                    ],
                ]]
            );
        }
    }
    
    /**
     * Send approval notification
     */
    private function sendApprovalNotification(int $postId): void
    {
        $approverId = get_post_meta($postId, '_sseo_ai_approver', true);
        if (!$approverId) return;
        
        $approver = get_userdata($approverId);
        if (!$approver) return;
        
        $post = get_post($postId);
        $notifyEmail = get_option('sseo_ai_notify_email', true);
        $notifySlack = get_option('sseo_ai_notify_slack', false);
        
        $message = sprintf(
            __('Content ready for review: %s', 'ai-seo-client'),
            $post->post_title
        );
        
        // Email notification
        if ($notifyEmail) {
            wp_mail(
                $approver->user_email,
                __('Content Approval Required', 'ai-seo-client'),
                $message . "\n\n" . get_edit_post_link($postId)
            );
        }
        
        // Slack notification
        if ($notifySlack) {
            $externalIntegrations = new ExternalIntegrations($this->settings);
            $externalIntegrations->sendSlackNotification(
                ':bell: ' . $message,
                [[
                    'color' => 'warning',
                    'fields' => [
                        ['title' => 'Approver', 'value' => $approver->display_name, 'short' => true],
                        ['title' => 'Content', 'value' => $post->post_title, 'short' => true],
                    ],
                ]]
            );
        }
    }
    
    /**
     * Handle post status change
     */
    public function handleStatusChange(string $newStatus, string $oldStatus, \WP_Post $post): void
    {
        if ($post->post_type !== 'post') {
            return;
        }
        
        // Auto-update workflow status based on post status
        if ($newStatus === 'publish' && $oldStatus !== 'publish') {
            update_post_meta($post->ID, '_sseo_ai_workflow_status', 'approved');
        }
    }
    
    /**
     * AJAX: Approve content
     */
    public function ajaxApproveContent(): void
    {
        check_ajax_referer('sseo_calendar', 'nonce');
        
        if (!current_user_can('edit_others_posts')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }
        
        $postId = (int)($_POST['post_id'] ?? 0);
        
        if (!$postId) {
            wp_send_json_error(['message' => 'Post ID required']);
        }
        
        update_post_meta($postId, '_sseo_ai_workflow_status', 'approved');
        
        // Optionally publish the post
        wp_publish_post($postId);
        
        wp_send_json_success(['message' => 'Content approved']);
    }
    
    /**
     * Register REST API routes
     */
    public function registerRestRoutes(): void
    {
        register_rest_route('sseo-ai/v1', '/calendar/optimize', [
            'methods' => 'POST',
            'callback' => [$this, 'restOptimizeSchedule'],
            'permission_callback' => function() {
                return current_user_can('edit_posts');
            },
        ]);
    }
    
    /**
     * REST: Optimize publishing schedule
     */
    public function restOptimizeSchedule(): array
    {
        // Analyze best publishing times based on historical performance
        $recommendations = $this->analyzePublishingTimes();
        
        return [
            'success' => true,
            'message' => 'Schedule optimized',
            'recommendations' => $recommendations,
        ];
    }
    
    /**
     * Analyze best publishing times
     */
    private function analyzePublishingTimes(): array
    {
        // This would analyze when posts get the most engagement
        // Placeholder implementation
        
        return [
            'best_day' => 'Tuesday',
            'best_time' => '10:00 AM',
            'frequency' => 'Weekly',
        ];
    }
}
