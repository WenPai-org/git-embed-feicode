<?php
declare(strict_types=1);

/**
 * Plugin Name: Git Embed for feiCode
 * Description: Embed Git repositories from GitHub with beautiful cards
 * Version: 1.0.0
 * Author: feiCode
 * Text Domain: git-embed-feicode
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) {
    exit;
}

class GitEmbedFeiCode {
    
    private const PLUGIN_VERSION = '1.0.0';
    private const BLOCK_NAME = 'git-embed-feicode/repository';
    
    public function __construct() {
        add_action('init', [$this, 'init']);
        add_action('wp_ajax_git_embed_fetch', [$this, 'ajax_fetch_repo']);
        add_action('wp_ajax_nopriv_git_embed_fetch', [$this, 'ajax_fetch_repo']);
    }
    
    public function init(): void {
        $this->register_block();
        $this->load_textdomain();
    }
    
    private function register_block(): void {
        register_block_type(self::BLOCK_NAME, [
            'editor_script' => 'git-embed-feicode-editor',
            'editor_style' => 'git-embed-feicode-style',  
            'style' => 'git-embed-feicode-style',
            'render_callback' => [$this, 'render_block'],
            'attributes' => [
                'platform' => [
                    'type' => 'string',
                    'default' => 'github'
                ],
                'owner' => [
                    'type' => 'string',
                    'default' => ''
                ],
                'repo' => [
                    'type' => 'string', 
                    'default' => ''
                ],
                'showDescription' => [
                    'type' => 'boolean',
                    'default' => true
                ],
                'showStats' => [
                    'type' => 'boolean',
                    'default' => true
                ],
                'showLanguage' => [
                    'type' => 'boolean',
                    'default' => true
                ],
                'showActions' => [
                    'type' => 'boolean',
                    'default' => true
                ],
                'showViewButton' => [
                    'type' => 'boolean',
                    'default' => true
                ],
                'showCloneButton' => [
                    'type' => 'boolean',
                    'default' => true
                ],
                'showDownloadButton' => [
                    'type' => 'boolean',
                    'default' => true
                ],
                'cardStyle' => [
                    'type' => 'string',
                    'default' => 'default'
                ],
                'buttonStyle' => [
                    'type' => 'string',
                    'default' => 'primary'
                ],
                'alignment' => [
                    'type' => 'string',
                    'default' => 'none'
                ]
            ]
        ]);
        
        wp_register_script(
            'git-embed-feicode-editor',
            plugin_dir_url(__FILE__) . 'block.js',
            ['wp-blocks', 'wp-element', 'wp-editor', 'wp-components', 'wp-i18n'],
            self::PLUGIN_VERSION
        );
        
        wp_register_style(
            'git-embed-feicode-style',
            plugin_dir_url(__FILE__) . 'style.css',
            [],
            self::PLUGIN_VERSION
        );
        
        wp_localize_script('git-embed-feicode-editor', 'gitEmbedAjax', [
            'url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('git_embed_nonce')
        ]);
    }
    
    public function render_block(array $attributes): string {
        $owner = sanitize_text_field($attributes['owner'] ?? '');
        $repo = sanitize_text_field($attributes['repo'] ?? '');
        $platform = sanitize_text_field($attributes['platform'] ?? 'github');
        
        if (empty($owner) || empty($repo)) {
            return '<div class="git-embed-error">Repository information required</div>';
        }
        
        $repo_data = $this->fetch_repository_data($platform, $owner, $repo);
        
        if (!$repo_data) {
            return '<div class="git-embed-error">Failed to fetch repository data</div>';
        }
        
        return $this->render_repository_card($repo_data, $attributes);
    }
    
    private function fetch_repository_data(string $platform, string $owner, string $repo): ?array {
        if ($platform !== 'github') {
            return null;
        }
        
        $cache_key = "git_embed_{$platform}_{$owner}_{$repo}";
        $cached = get_transient($cache_key);
        
        if ($cached !== false) {
            return $cached;
        }
        
        $url = "https://api.github.com/repos/{$owner}/{$repo}";
        $response = wp_remote_get($url, [
            'timeout' => 10,
            'headers' => [
                'User-Agent' => 'Git-Embed-FeiCode/1.0'
            ]
        ]);
        
        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            return null;
        }
        
        $data = json_decode(wp_remote_retrieve_body($response), true);
        
        if (!$data) {
            return null;
        }
        
        $repo_data = [
            'name' => $data['name'],
            'full_name' => $data['full_name'],
            'description' => $data['description'],
            'html_url' => $data['html_url'],
            'language' => $data['language'],
            'stargazers_count' => $data['stargazers_count'],
            'forks_count' => $data['forks_count'],
            'open_issues_count' => $data['open_issues_count'],
            'clone_url' => $data['clone_url'],
            'archive_url' => $data['archive_url']
        ];
        
        set_transient($cache_key, $repo_data, HOUR_IN_SECONDS);
        
        return $repo_data;
    }
    
    private function render_repository_card(array $repo_data, array $attributes): string {
        $show_description = $attributes['showDescription'] ?? true;
        $show_stats = $attributes['showStats'] ?? true;
        $show_language = $attributes['showLanguage'] ?? true;
        $show_actions = $attributes['showActions'] ?? true;
        $show_view_button = $attributes['showViewButton'] ?? true;
        $show_clone_button = $attributes['showCloneButton'] ?? true;
        $show_download_button = $attributes['showDownloadButton'] ?? true;
        $card_style = $attributes['cardStyle'] ?? 'default';
        $button_style = $attributes['buttonStyle'] ?? 'primary';
        $alignment = $attributes['alignment'] ?? 'none';
        
        $align_class = $alignment !== 'none' ? " align{$alignment}" : '';
        $card_class = $card_style !== 'default' ? " git-embed-card-{$card_style}" : '';
        
        $download_url = str_replace('{archive_format}', 'zipball', $repo_data['archive_url']);
        $download_url = str_replace('{/ref}', '/main', $download_url);
        
        ob_start();
        ?>
        <div class="wp-block-git-embed-feicode-repository<?php echo esc_attr($align_class); ?>">
            <div class="git-embed-card<?php echo esc_attr($card_class); ?>">
                <div class="git-embed-header">
                    <h3 class="git-embed-title">
                        <span class="dashicons dashicons-admin-links git-embed-repo-icon"></span>
                        <a href="<?php echo esc_url($repo_data['html_url']); ?>" target="_blank" rel="noopener">
                            <?php echo esc_html($repo_data['full_name']); ?>
                        </a>
                    </h3>
                    <?php if ($show_language && $repo_data['language']): ?>
                        <span class="git-embed-language">
                            <span class="dashicons dashicons-editor-code"></span>
                            <?php echo esc_html($repo_data['language']); ?>
                        </span>
                    <?php endif; ?>
                </div>
                
                <?php if ($show_description && $repo_data['description']): ?>
                    <p class="git-embed-description">
                        <span class="dashicons dashicons-text-page"></span>
                        <?php echo esc_html($repo_data['description']); ?>
                    </p>
                <?php endif; ?>
                
                <?php if ($show_stats): ?>
                    <div class="git-embed-stats">
                        <span class="git-embed-stat">
                            <span class="dashicons dashicons-star-filled"></span>
                            <span class="git-embed-stat-label">Stars:</span>
                            <span class="git-embed-stat-value"><?php echo number_format_i18n($repo_data['stargazers_count']); ?></span>
                        </span>
                        <span class="git-embed-stat">
                            <span class="dashicons dashicons-networking"></span>
                            <span class="git-embed-stat-label">Forks:</span>
                            <span class="git-embed-stat-value"><?php echo number_format_i18n($repo_data['forks_count']); ?></span>
                        </span>
                        <span class="git-embed-stat">
                            <span class="dashicons dashicons-editor-help"></span>
                            <span class="git-embed-stat-label">Issues:</span>
                            <span class="git-embed-stat-value"><?php echo number_format_i18n($repo_data['open_issues_count']); ?></span>
                        </span>
                    </div>
                <?php endif; ?>
                
                <?php if ($show_actions && ($show_view_button || $show_clone_button || $show_download_button)): ?>
                    <div class="git-embed-actions">
                        <?php if ($show_view_button): ?>
                            <a href="<?php echo esc_url($repo_data['html_url']); ?>" 
                               class="git-embed-button git-embed-button-<?php echo esc_attr($button_style); ?>" 
                               target="_blank" rel="noopener">
                                <span class="dashicons dashicons-external"></span>
                                View Repository
                            </a>
                        <?php endif; ?>
                        
                        <?php if ($show_clone_button): ?>
                            <button type="button" 
                                    class="git-embed-button git-embed-button-secondary git-embed-clone-btn" 
                                    data-clone-url="<?php echo esc_attr($repo_data['clone_url']); ?>"
                                    title="Click to copy clone URL">
                                <span class="dashicons dashicons-admin-page"></span>
                                Clone
                            </button>
                        <?php endif; ?>
                        
                        <?php if ($show_download_button): ?>
                            <a href="<?php echo esc_url($download_url); ?>" 
                               class="git-embed-button git-embed-button-secondary"
                               download="<?php echo esc_attr($repo_data['name']); ?>.zip">
                                <span class="dashicons dashicons-download"></span>
                                Download ZIP
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <?php if ($show_clone_button): ?>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            const cloneButtons = document.querySelectorAll('.git-embed-clone-btn');
            cloneButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const cloneUrl = this.dataset.cloneUrl;
                    if (navigator.clipboard) {
                        navigator.clipboard.writeText(cloneUrl).then(() => {
                            const originalText = this.innerHTML;
                            this.innerHTML = '<span class="dashicons dashicons-yes"></span>Copied!';
                            setTimeout(() => {
                                this.innerHTML = originalText;
                            }, 2000);
                        });
                    } else {
                        const input = document.createElement('input');
                        input.value = cloneUrl;
                        document.body.appendChild(input);
                        input.select();
                        document.execCommand('copy');
                        document.body.removeChild(input);
                        
                        const originalText = this.innerHTML;
                        this.innerHTML = '<span class="dashicons dashicons-yes"></span>Copied!';
                        setTimeout(() => {
                            this.innerHTML = originalText;
                        }, 2000);
                    }
                });
            });
        });
        </script>
        <?php endif; ?>
        <?php
        return ob_get_clean();
    }
    
    public function ajax_fetch_repo(): void {
        check_ajax_referer('git_embed_nonce', 'nonce');
        
        $platform = sanitize_text_field($_POST['platform'] ?? '');
        $owner = sanitize_text_field($_POST['owner'] ?? '');
        $repo = sanitize_text_field($_POST['repo'] ?? '');
        
        if (empty($owner) || empty($repo)) {
            wp_send_json_error('Repository information required');
        }
        
        $repo_data = $this->fetch_repository_data($platform, $owner, $repo);
        
        if (!$repo_data) {
            wp_send_json_error('Failed to fetch repository data');
        }
        
        wp_send_json_success($repo_data);
    }
    
    private function load_textdomain(): void {
        load_plugin_textdomain(
            'git-embed-feicode',
            false,
            dirname(plugin_basename(__FILE__)) . '/languages'
        );
    }
}

new GitEmbedFeiCode();