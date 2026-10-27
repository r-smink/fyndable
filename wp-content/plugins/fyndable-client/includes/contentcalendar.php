<?php

namespace SSEOAIClient;

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
    private LicenseValidator $licenseValidator;

    public function __construct(Settings $settings, LLMClient $llm, LicenseValidator $licenseValidator)
    {
        $this->settings = $settings;
        $this->llm = $llm;
        $this->licenseValidator = $licenseValidator;
    }

    public function register(): void
    {
        // Menu registration moved to Client class
        add_action('admin_init', [$this, 'registerSettings']);
        add_action('init', [$this, 'registerCustomStatuses']);

        // Advanced workflow features (custom statuses, approvals, assignments) are Business+ only
        if ($this->licenseValidator->isBusinessPlus()) {
            add_action('rest_api_init', [$this, 'registerRestRoutes']);
            add_action('transition_post_status', [$this, 'handleStatusChange'], 10, 3);
            add_action('wp_ajax_sseo_ai_approve_content', [$this, 'ajaxApproveContent']);
            add_action('wp_ajax_sseo_ai_move_draft', [$this, 'ajaxMoveDraft']);

            // Meta box moved to PostMetaBox tabbed container
            add_action('save_post', [$this, 'saveWorkflowMetaBox'], 10, 2);
        }
    }

    /**
     * Register custom workflow statuses.
     */
    public function registerCustomStatuses(): void
    {
        $statuses = [
            'sseo_ideation' => __('Ideation', 'ai-seo-client'),
            'sseo_in_progress' => __('In Progress', 'ai-seo-client'),
            'sseo_review' => __('In Review', 'ai-seo-client'),
            'sseo_revision' => __('Revision Needed', 'ai-seo-client'),
            'sseo_approved' => __('Approved', 'ai-seo-client'),
            'sseo_scheduled' => __('Scheduled', 'ai-seo-client'),
        ];

        foreach ($statuses as $slug => $label) {
            register_post_status($slug, [
                'label' => $label,
                'public' => false,
                'protected' => true,
                'show_in_admin_status_list' => true,
                'label_count' => _n_noop("{$label} <span class=\"count\">(%s)</span>", "{$label} <span class=\"count\">(%s)</span>", 'ai-seo-client'),
            ]);
        }
    }
    
    public function addMenu(): void
    {
        add_submenu_page(
            'ai-seo-client',
            __('Content Calendar', 'ai-seo-client'),
            __('Content Calendar', 'ai-seo-client'),
            'manage_options',
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
        <style>
            .fyn-calendar { width: 100%; border-collapse: collapse; }
            .fyn-calendar th { background: var(--well); color: var(--text-m); padding: 10px; text-align: center; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; border-bottom: 2px solid var(--divide); }
            .fyn-calendar td { border: 1px solid var(--divide); padding: 8px; vertical-align: top; min-height: 86px; height: 86px; }
            .fyn-calendar .day-number { font-weight: 700; font-size: 13px; color: var(--text-h); margin-bottom: 4px; }
            .fyn-calendar .content-item { padding: 4px 8px; margin: 3px 0; border-radius: 7px; font-size: 11.5px; font-weight: 600; cursor: pointer; transition: all .15s; }
            .fyn-calendar .content-item.draft { background: var(--fyn-warning-light); color: var(--fyn-warning-text); }
            .fyn-calendar .content-item.pending { background: var(--fyn-info-light); color: var(--fyn-info-text); }
            .fyn-calendar .content-item.future, .fyn-calendar .content-item.scheduled { background: var(--fyn-accent-light); color: var(--fyn-accent-text); }
            .fyn-calendar .content-item.publish { background: var(--fyn-success-light); color: var(--fyn-success-text); }
            .fyn-calendar .today { background: var(--row-hover); }
            .fyn-calendar td.drag-over { background: var(--well); border: 2px dashed var(--fyn-purple); }
            .fyn-calendar .content-item.draft, .fyn-calendar .content-item.future { cursor: grab; }
            .fyn-calendar .content-item.draft:active, .fyn-calendar .content-item.future:active { cursor: grabbing; }
            .fyn-calendar .content-item.dragging { opacity: 0.5; }
            .fyn-cal-columns { display: grid; grid-template-columns: 2.5fr 1fr; gap: 20px; }
            @media (max-width: 1024px) { .fyn-cal-columns { grid-template-columns: 1fr; } }
        </style>
        <div class="wrap fyn-animate-in" style="max-width:1440px;margin:0 auto;padding:28px 32px 48px;">

            <!-- Page header -->
            <div class="fyn-page-header">
                <div class="fyn-page-header-left">
                    <div class="fyn-breadcrumb">Fyndable &rsaquo; <span class="fyn-crumb"><?php esc_html_e('Content Calendar', 'ai-seo-client'); ?></span></div>
                    <h1><?php esc_html_e('Content Calendar & Workflow', 'ai-seo-client'); ?></h1>
                    <p class="fyn-page-desc"><?php esc_html_e('Plan and manage your publishing schedule, discover content gaps.', 'ai-seo-client'); ?></p>
                </div>
                <div class="fyn-page-header-actions">
                    <button type="button" class="fyn-btn fyn-btn-outline" onclick="sseoOptimizeSchedule()">
                        <span class="dashicons dashicons-admin-generic"></span>
                        <?php esc_html_e('Optimize Schedule', 'ai-seo-client'); ?>
                    </button>
                    <button type="button" class="fyn-btn fyn-btn-primary" onclick="sseoScheduleContent()">
                        <span class="dashicons dashicons-plus-alt2"></span>
                        <?php esc_html_e('Schedule New Content', 'ai-seo-client'); ?>
                    </button>
                </div>
            </div>

            <?php DashboardSorter::begin('ai-seo-content-calendar'); ?>

            <!-- Month navigation -->
            <div class="fyn-card" style="margin-bottom:20px;padding:14px 22px;">
                <div style="display:flex;justify-content:space-between;align-items:center;">
                    <a href="<?php echo admin_url('admin.php?page=ai-seo-content-calendar&month=' . ($currentMonth - 1) . '&year=' . $currentYear); ?>" class="fyn-btn fyn-btn-outline" style="padding:7px 14px;">
                        &larr; <?php esc_html_e('Previous', 'ai-seo-client'); ?>
                    </a>
                    <span style="font-size:18px;font-weight:700;color:var(--text-h);">
                        <?php echo date('F Y', mktime(0, 0, 0, $currentMonth, 1, $currentYear)); ?>
                    </span>
                    <a href="<?php echo admin_url('admin.php?page=ai-seo-content-calendar&month=' . ($currentMonth + 1) . '&year=' . $currentYear); ?>" class="fyn-btn fyn-btn-outline" style="padding:7px 14px;">
                        <?php esc_html_e('Next', 'ai-seo-client'); ?> &rarr;
                    </a>
                </div>
            </div>

            <div class="fyn-cal-columns">
                <!-- Visual Calendar -->
                <div class="fyn-card">
                    <div class="fyn-card-header">
                        <span class="dashicons dashicons-calendar-alt"></span>
                        <h3 class="fyn-card-title"><?php esc_html_e('Publishing Calendar', 'ai-seo-client'); ?></h3>
                    </div>
                
                <table class="fyn-calendar">
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
                            <?php for ($dayOfWeek = 1; $dayOfWeek <= 7; $dayOfWeek++):
                                $isValidDay = ($week === 0 && $dayOfWeek >= $firstDay) || ($week > 0 && $day <= $daysInMonth);
                                $currentDate = sprintf('%04d-%02d-%02d', $currentYear, $currentMonth, $day);
                                $cellClasses = $isValidDay && $currentDate === $today ? 'today' : '';
                            ?>
                            <td class="<?php echo esc_attr($cellClasses); ?>"
                                <?php if ($isValidDay): ?>
                                data-date="<?php echo esc_attr($currentDate); ?>"
                                ondragover="sseoDragOver(event)"
                                ondrop="sseoDrop(event)"
                                ondragenter="sseoDragEnter(event)"
                                ondragleave="sseoDragLeave(event)"
                                <?php endif; ?>>
                                <?php if ($isValidDay): ?>
                                    <div class="day-number"><?php echo $day; ?></div>
                                    
                                    <?php
                                    $dateKey = $currentDate;
                                    if (isset($calendarData[$dateKey])):
                                        foreach ($calendarData[$dateKey] as $item):
                                    ?>
                                    <div class="content-item <?php echo esc_attr($item['status']); ?>" 
                                         <?php if (in_array($item['status'], ['draft', 'future'])): ?>
                                         draggable="true"
                                         ondragstart="sseoDragStart(event)"
                                         data-post-id="<?php echo esc_attr($item['id']); ?>"
                                         <?php endif; ?>
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
                
                </div>
                
                <!-- Right Column -->
                <div style="display:flex;flex-direction:column;gap:20px;">
                    <!-- Content Gap Analysis -->
                    <div class="fyn-card">
                        <div class="fyn-card-header">
                            <span class="dashicons dashicons-warning"></span>
                            <h3 class="fyn-card-title"><?php esc_html_e('Content Gap Calendar', 'ai-seo-client'); ?></h3>
                        </div>
                <?php if (!empty($contentGaps)): ?>
                    <p style="font-size:13px;color:var(--text-m);margin:0 0 12px;"><?php esc_html_e('Gaps in your content calendar:', 'ai-seo-client'); ?></p>
                    <?php foreach (array_slice($contentGaps, 0, 10) as $gap): ?>
                    <div style="display:flex;align-items:center;justify-content:space-between;padding:8px 0;border-bottom:1px solid var(--divide);">
                        <span style="font-size:13px;font-weight:600;color:var(--text-b);"><?php echo esc_html(date('j F Y', strtotime($gap))); ?></span>
                        <button type="button" class="fyn-btn fyn-btn-outline" style="padding:5px 12px;font-size:12px;" onclick="sseoFillGap('<?php echo esc_js($gap); ?>')">
                            <?php esc_html_e('Fill Gap', 'ai-seo-client'); ?>
                        </button>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p><span class="fyn-badge fyn-badge--success"><?php esc_html_e('No gaps found!', 'ai-seo-client'); ?></span></p>
                <?php endif; ?>
                    </div>

                    <!-- Workflow Status -->
                    <div class="fyn-card">
                        <div class="fyn-card-header">
                            <span class="dashicons dashicons-admin-post"></span>
                            <h3 class="fyn-card-title"><?php esc_html_e('Workflow Status', 'ai-seo-client'); ?></h3>
                        </div>
                
                <?php
                $workflowStats = $this->getWorkflowStats();
                ?>
                
                <div class="fyn-stats-grid" style="margin-bottom:16px;">
                    <div class="fyn-stat-tile">
                        <div class="fyn-stat-value"><?php echo esc_html($workflowStats['draft']); ?></div>
                        <div class="fyn-stat-label"><?php esc_html_e('Drafts', 'ai-seo-client'); ?></div>
                    </div>
                    <div class="fyn-stat-tile">
                        <div class="fyn-stat-value"><?php echo esc_html($workflowStats['pending']); ?></div>
                        <div class="fyn-stat-label"><?php esc_html_e('In Review', 'ai-seo-client'); ?></div>
                    </div>
                    <div class="fyn-stat-tile">
                        <div class="fyn-stat-value"><?php echo esc_html($workflowStats['scheduled']); ?></div>
                        <div class="fyn-stat-label"><?php esc_html_e('Scheduled', 'ai-seo-client'); ?></div>
                    </div>
                    <div class="fyn-stat-tile">
                        <div class="fyn-stat-value"><?php echo esc_html($workflowStats['published']); ?></div>
                        <div class="fyn-stat-label"><?php esc_html_e('Published (30d)', 'ai-seo-client'); ?></div>
                    </div>
                </div>

                <h4 style="margin:16px 0 10px;font-size:14px;font-weight:700;color:var(--text-h);"><?php esc_html_e('Pending Approval', 'ai-seo-client'); ?></h4>
                <?php
                $pendingApprovals = $this->getPendingApprovals();
                if (!empty($pendingApprovals)):
                ?>
                <table class="fyn-table">
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
                                <a href="<?php echo get_edit_post_link($approval['post_id']); ?>" style="color:var(--fyn-purple);font-weight:600;">
                                    <?php echo esc_html($approval['title']); ?>
                                </a>
                            </td>
                            <td><?php echo esc_html($approval['author']); ?></td>
                            <td><?php echo esc_html($approval['assigned_to']); ?></td>
                            <td><?php echo esc_html(human_time_diff(strtotime($approval['submitted']))); ?> <?php esc_html_e('ago', 'ai-seo-client'); ?></td>
                            <td style="display:flex;gap:6px;">
                                <button type="button" class="fyn-btn fyn-btn-primary" style="padding:5px 12px;font-size:12px;"
                                        onclick="sseoApproveContent(<?php echo $approval['post_id']; ?>)">
                                    <?php esc_html_e('Approve', 'ai-seo-client'); ?>
                                </button>
                                <button type="button" class="fyn-btn fyn-btn-outline" style="padding:5px 12px;font-size:12px;"
                                        onclick="sseoRejectContent(<?php echo $approval['post_id']; ?>)">
                                    <?php esc_html_e('Reject', 'ai-seo-client'); ?>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <p style="font-size:13px;color:var(--text-m);"><?php esc_html_e('No pending approvals.', 'ai-seo-client'); ?></p>
                <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Keyword Opportunity Timeline -->
            <div class="fyn-card" style="margin-top:20px;">
                <div class="fyn-card-header">
                    <span class="dashicons dashicons-trending-up"></span>
                    <h3 class="fyn-card-title"><?php esc_html_e('Keyword Opportunity Timeline', 'ai-seo-client'); ?></h3>
                </div>
                    
                <?php if (!empty($keywordOpportunities)): ?>
                <table class="fyn-table">
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
                            <td><strong style="color:var(--text-h);"><?php echo esc_html($opp['keyword']); ?></strong></td>
                            <td><?php echo esc_html($opp['type']); ?></td>
                            <td><?php echo esc_html(date('j M Y', strtotime($opp['suggested_date']))); ?></td>
                            <td>
                                <span class="fyn-badge fyn-badge--<?php echo $opp['priority'] === 'high' ? 'danger' : 'warning'; ?>">
                                    <?php echo esc_html(ucfirst($opp['priority'])); ?>
                                </span>
                            </td>
                            <td>
                                <button type="button" class="fyn-btn fyn-btn-outline" style="padding:5px 12px;font-size:12px;"
                                        onclick="sseoCreateFromOpportunity('<?php echo esc_js($opp['keyword']); ?>', '<?php echo esc_js($opp['suggested_date']); ?>')">
                                    <?php esc_html_e('Create Content', 'ai-seo-client'); ?>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <p style="font-size:13px;color:var(--text-m);"><?php esc_html_e('No keyword opportunities found yet.', 'ai-seo-client'); ?></p>
                <?php endif; ?>
            </div>

        <?php DashboardSorter::end('ai-seo-content-calendar'); ?>
        </div>
        
        <script>
        function sseoEditContent(postId) {
            window.open('<?php echo admin_url('post.php?action=edit&post='); ?>' + postId, '_blank');
        }
        
        function sseoScheduleContent() {
            window.open('<?php echo admin_url('post-new.php'); ?>', '_blank');
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
            window.open('<?php echo admin_url('post-new.php?suggested_date='); ?>' + date, '_blank');
        }

        function sseoCreatePageFromOpportunity(keyword, date) {
            window.open('<?php echo admin_url('post-new.php?post_type=page&keyword='); ?>' + encodeURIComponent(keyword) + '&suggested_date=' + date, '_blank');
        }
        
        function sseoCreateFromOpportunity(keyword, date) {
            window.open('<?php echo admin_url('post-new.php?keyword='); ?>' + encodeURIComponent(keyword) + '&suggested_date=' + date, '_blank');
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

        // Drag & Drop for draft posts
        let draggedEl = null;

        function sseoDragStart(e) {
            draggedEl = e.target;
            e.target.classList.add('dragging');
            e.dataTransfer.setData('text/plain', e.target.dataset.postId);
            e.dataTransfer.effectAllowed = 'move';
        }

        function sseoDragOver(e) {
            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';
        }

        function sseoDragEnter(e) {
            e.preventDefault();
            const cell = e.target.closest('td[data-date]');
            if (cell) {
                cell.classList.add('drag-over');
            }
        }

        function sseoDragLeave(e) {
            const cell = e.target.closest('td[data-date]');
            if (cell) {
                cell.classList.remove('drag-over');
            }
        }

        function sseoDrop(e) {
            e.preventDefault();
            const cell = e.target.closest('td[data-date]');
            if (!cell) return;

            cell.classList.remove('drag-over');

            const postId = e.dataTransfer.getData('text/plain');
            const date = cell.dataset.date;
            if (!postId || !date) return;

            sseoMoveDraft(postId, date, draggedEl);
        }

        function sseoMoveDraft(postId, date, el) {
            jQuery.post(ajaxurl, {
                action: 'sseo_ai_move_draft',
                post_id: postId,
                date: date,
                nonce: '<?php echo wp_create_nonce('sseo_calendar'); ?>'
            }, function(response) {
                if (el) el.classList.remove('dragging');
                if (response.success) {
                    location.reload();
                } else {
                    alert(response.data.message || 'Error scheduling post');
                }
            });
        }

        document.addEventListener('dragend', function(e) {
            if (e.target.classList.contains('content-item')) {
                e.target.classList.remove('dragging');
            }
        });
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
                    <option value="sseo_ideation" <?php selected($workflowStatus, 'sseo_ideation'); ?>><?php esc_html_e('Ideation', 'ai-seo-client'); ?></option>
                    <option value="sseo_in_progress" <?php selected($workflowStatus, 'sseo_in_progress'); ?>><?php esc_html_e('In Progress', 'ai-seo-client'); ?></option>
                    <option value="sseo_review" <?php selected($workflowStatus, 'sseo_review'); ?>><?php esc_html_e('In Review', 'ai-seo-client'); ?></option>
                    <option value="sseo_revision" <?php selected($workflowStatus, 'sseo_revision'); ?>><?php esc_html_e('Revision Needed', 'ai-seo-client'); ?></option>
                    <option value="approved" <?php selected($workflowStatus, 'approved'); ?>><?php esc_html_e('Approved', 'ai-seo-client'); ?></option>
                    <option value="sseo_scheduled" <?php selected($workflowStatus, 'sseo_scheduled'); ?>><?php esc_html_e('Scheduled', 'ai-seo-client'); ?></option>
                    <option value="rejected" <?php selected($workflowStatus, 'rejected'); ?>><?php esc_html_e('Rejected', 'ai-seo-client'); ?></option>
                </select>
            </p>

            <?php if (in_array($workflowStatus, ['pending_review', 'sseo_review']) && current_user_can('edit_others_posts')): ?>
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

            // Send notification if status changed to a review state
            $reviewStatuses = ['pending_review', 'sseo_review'];
            if (!in_array($oldStatus, $reviewStatuses, true) && in_array($newStatus, $reviewStatuses, true)) {
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
            SELECT ID, post_title, post_status, post_date, post_modified
            FROM {$wpdb->posts}
            WHERE post_type IN ('post', 'page')
            AND (
                (post_status IN ('publish', 'future') AND post_date BETWEEN %s AND %s)
                OR (post_status IN ('draft', 'pending') AND post_modified BETWEEN %s AND %s)
            )
            ORDER BY post_date ASC
        ", $startDate, $endDate . ' 23:59:59', $startDate, $endDate . ' 23:59:59'));
        
        $calendar = [];
        foreach ($posts as $post) {
            $date = date('Y-m-d', strtotime(
                in_array($post->post_status, ['draft', 'pending'], true) ? $post->post_modified : $post->post_date
            ));
            
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
                AND post_status IN ('publish', 'future', 'draft', 'pending')
                AND DATE(post_date) = %s
            ", $day));
            
            if (!$hasContent) {
                $gaps[] = $day;
            }
        }
        
        return $gaps;
    }
    
    /**
     * Get keyword opportunities from existing site data
     */
    private function getKeywordOpportunities(): array
    {
        global $wpdb;
        $opportunities = [];
        
        // Get top performing keywords from rank tracker if available
        $rankTable = $wpdb->prefix . 'sseo_ai_tracked_keywords';
        $historyTable = $wpdb->prefix . 'sseo_ai_rank_history';
        $tableExists = $wpdb->get_var("SHOW TABLES LIKE '{$rankTable}'") === $rankTable;
        
        if ($tableExists) {
            // Get keywords that improved in ranking recently (opportunity to create more content)
            $improvingKeywords = $wpdb->get_results("
                SELECT tk.keyword, tk.url, MIN(rh.position) as best_position,
                       COUNT(rh.id) as check_count
                FROM {$rankTable} tk
                LEFT JOIN {$historyTable} rh ON tk.id = rh.keyword_id
                WHERE rh.created_at > DATE_SUB(NOW(), INTERVAL 14 DAY)
                GROUP BY tk.id
                HAVING best_position <= 20 AND check_count >= 2
                ORDER BY best_position ASC
                LIMIT 5
            ");
            
            foreach ($improvingKeywords as $kw) {
                $opportunities[] = [
                    'keyword' => $kw->keyword,
                    'type' => 'Improving Rank',
                    'suggested_date' => date('Y-m-d', strtotime('+7 days')),
                    'priority' => $kw->best_position <= 10 ? 'high' : 'medium',
                ];
            }
        }
        
        // Get content gaps - popular tags/categories with few posts
        $popularTerms = $wpdb->get_results("
            SELECT t.name, t.slug, COUNT(p.ID) as post_count,
                   tt.taxonomy
            FROM {$wpdb->terms} t
            JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
            JOIN {$wpdb->term_relationships} tr ON tt.term_taxonomy_id = tr.term_taxonomy_id
            JOIN {$wpdb->posts} p ON tr.object_id = p.ID
            WHERE p.post_status = 'publish'
            AND p.post_type = 'post'
            AND tt.taxonomy IN ('category', 'post_tag')
            GROUP BY t.term_id
            HAVING post_count >= 1 AND post_count <= 3
            ORDER BY post_count ASC
            LIMIT 5
        ");
        
        foreach ($popularTerms as $term) {
            $opportunities[] = [
                'keyword' => $term->name,
                'type' => 'Content Gap (' . ucfirst($term->taxonomy) . ')',
                'suggested_date' => date('Y-m-d', strtotime('+'.rand(3,14).' days')),
                'priority' => 'medium',
            ];
        }
        
        return $opportunities;
    }
    
    /**
     * Get workflow stats
     */
    private function getWorkflowStats(): array
    {
        global $wpdb;

        $postIds = $wpdb->get_col("SELECT ID FROM {$wpdb->posts} WHERE post_type = 'post' AND post_status IN ('draft','pending','future','publish','sseo_ideation','sseo_in_progress','sseo_review','sseo_revision','sseo_approved','sseo_scheduled')");
        $counts = ['draft' => 0, 'pending' => 0, 'scheduled' => 0, 'published' => 0];

        foreach ($postIds as $postId) {
            $workflowStatus = get_post_meta((int) $postId, '_sseo_ai_workflow_status', true);
            if (empty($workflowStatus)) {
                $workflowStatus = 'draft';
            }

            if (in_array($workflowStatus, ['draft', 'sseo_ideation'], true)) {
                $counts['draft']++;
            } elseif (in_array($workflowStatus, ['in_progress', 'sseo_in_progress', 'sseo_revision'], true)) {
                $counts['pending']++;
            } elseif (in_array($workflowStatus, ['pending_review', 'sseo_review'], true)) {
                $counts['pending']++;
            } elseif (in_array($workflowStatus, ['approved', 'sseo_approved', 'scheduled', 'sseo_scheduled'], true)) {
                $counts['scheduled']++;
            }

            $post = get_post($postId);
            if ($post && $post->post_status === 'publish' && $post->post_date > date('Y-m-d H:i:s', strtotime('-30 days'))) {
                $counts['published']++;
            }
        }

        return $counts;
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
            AND pm2.meta_value IN ('pending_review', 'sseo_review')
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
            update_post_meta($post->ID, '_sseo_ai_workflow_status', 'sseo_approved');
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
        
        update_post_meta($postId, '_sseo_ai_workflow_status', 'sseo_approved');

        // Optionally publish the post
        wp_publish_post($postId);

        wp_send_json_success(['message' => 'Content approved']);
    }
    
    /**
     * AJAX: Move a draft post to another date and schedule it
     */
    public function ajaxMoveDraft(): void
    {
        check_ajax_referer('sseo_calendar', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }
        
        $postId = (int)($_POST['post_id'] ?? 0);
        $date = sanitize_text_field($_POST['date'] ?? '');
        
        if (!$postId || !$date) {
            wp_send_json_error(['message' => 'Post ID and date required']);
        }
        
        $post = get_post($postId);
        if (!$post || !in_array($post->post_type, ['post', 'page'], true)) {
            wp_send_json_error(['message' => 'Post not found']);
        }
        
        if (!get_current_user_id() || !current_user_can('edit_post', $postId)) {
            wp_send_json_error(['message' => 'You cannot edit this post']);
        }
        
        $newDate = date('Y-m-d H:i:s', strtotime($date . ' 09:00:00'));
        $gmtDate = get_gmt_from_date($newDate);
        
        $result = wp_update_post([
            'ID' => $postId,
            'post_date' => $newDate,
            'post_date_gmt' => $gmtDate,
            'post_modified' => current_time('mysql'),
            'post_modified_gmt' => current_time('mysql', true),
            'post_status' => 'future',
        ], true);
        
        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }
        
        update_post_meta($postId, '_sseo_ai_workflow_status', 'sseo_scheduled');

        wp_send_json_success(['message' => 'Post scheduled']);
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

        register_rest_route('sseo-ai/v1', '/calendar/sync-cluster', [
            'methods' => 'POST',
            'callback' => [$this, 'restSyncFromCluster'],
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
     * Sync cluster calendar items into the Content Calendar.
     * Creates draft posts with scheduled dates based on the cluster's content calendar.
     */
    public function syncFromCluster(array $clusterData, string $startDate, int $gapDays = 3): array
    {
        $calendar = $clusterData['content_calendar'] ?? [];
        $allPages = $clusterData['all_pages'] ?? [];
        $synced = 0;
        $errors = [];

        // If no explicit all_pages provided, build from cluster structure
        if (empty($allPages)) {
            if (isset($clusterData['pillar_page'])) {
                $allPages[] = [
                    'title' => $clusterData['pillar_page']['title'],
                    'keyword' => $clusterData['pillar_page']['target_keyword'] ?? '',
                    'word_count' => $clusterData['pillar_page']['target_word_count'] ?? 3000,
                    'content_type' => 'pillar',
                ];
            }
            foreach ($clusterData['clusters'] ?? [] as $cl) {
                if (isset($cl['hub_page'])) {
                    $allPages[] = [
                        'title' => $cl['hub_page']['title'],
                        'keyword' => $cl['hub_page']['target_keyword'] ?? '',
                        'word_count' => $cl['hub_page']['target_word_count'] ?? 1500,
                        'content_type' => 'hub',
                    ];
                }
                foreach ($cl['supporting_pages'] ?? [] as $sp) {
                    $allPages[] = [
                        'title' => $sp['title'],
                        'keyword' => $sp['target_keyword'] ?? '',
                        'word_count' => $sp['target_word_count'] ?? 800,
                        'content_type' => 'supporting',
                    ];
                }
            }
        }

        $baseDate = strtotime($startDate);
        if (!$baseDate) {
            $baseDate = strtotime('+1 day');
        }

        foreach ($allPages as $idx => $page) {
            $scheduledDate = date('Y-m-d H:i:s', strtotime('+' . ($idx * $gapDays) . ' days', $baseDate));

            // Check if a post with this title already exists
            $existingQuery = new \WP_Query([
                'post_type' => 'post',
                'title' => $page['title'],
                'posts_per_page' => 1,
                'post_status' => ['draft', 'future', 'publish', 'pending'],
                'fields' => 'ids',
            ]);
            $existingId = $existingQuery->posts[0] ?? 0;
            wp_reset_postdata();
            if ($existingId) {
                $existing = get_post($existingId);
                // Update schedule if it's a draft
                if ($existing && $existing->post_status === 'draft') {
                    wp_update_post([
                        'ID' => $existing->ID,
                        'post_date' => $scheduledDate,
                        'post_date_gmt' => get_gmt_from_date($scheduledDate),
                        'post_status' => 'future',
                    ]);
                    update_post_meta($existing->ID, '_sseo_ai_calendar_synced', '1');
                    update_post_meta($existing->ID, '_sseo_ai_focus_keyphrase', $page['keyword'] ?? '');
                    $synced++;
                }
                continue;
            }

            // Create a placeholder scheduled draft post
            $postId = wp_insert_post([
                'post_title' => $page['title'],
                'post_content' => '',
                'post_type' => 'post',
                'post_status' => 'future',
                'post_author' => get_current_user_id(),
                'post_date' => $scheduledDate,
                'post_date_gmt' => get_gmt_from_date($scheduledDate),
                'meta_input' => [
                    '_sseo_ai_focus_keyphrase' => $page['keyword'] ?? '',
                    '_sseo_ai_calendar_synced' => '1',
                    '_sseo_ai_cluster_content_type' => $page['content_type'] ?? '',
                    '_sseo_ai_target_word_count' => $page['word_count'] ?? 1500,
                ],
            ]);

            if (is_wp_error($postId)) {
                $errors[] = $page['title'] . ': ' . $postId->get_error_message();
            } else {
                $synced++;
            }
        }

        return [
            'synced' => $synced,
            'errors' => $errors,
            'total' => count($allPages),
        ];
    }

    /**
     * REST: Sync cluster to content calendar
     */
    public function restSyncFromCluster(\WP_REST_Request $request): array|\WP_Error
    {
        $cluster = $request->get_param('cluster');
        $startDate = sanitize_text_field($request->get_param('start_date') ?? date('Y-m-d H:i:s', strtotime('+1 day')));
        $gapDays = (int) ($request->get_param('gap_days') ?? 3);

        if (empty($cluster)) {
            return new \WP_Error('missing', __('Cluster data required', 'ai-seo-client'), ['status' => 400]);
        }

        return $this->syncFromCluster($cluster, $startDate, $gapDays);
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
