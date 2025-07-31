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
                'showDownload' => [
                    'type' => 'boolean',
                    'default' => true
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
        $show_download = $attributes['showDownload'] ?? true;
        $alignment = $attributes['alignment'] ?? 'none';
        
        $align_class = $alignment !== 'none' ? " align{$alignment}" : '';
        
        ob_start();
        ?>
        <div class="wp-block-git-embed-feicode-repository<?php echo esc_attr($align_class); ?>">
            <div class="git-embed-card">
                <div class="git-embed-header">
                    <h3 class="git-embed-title">
                        <a href="<?php echo esc_url($repo_data['html_url']); ?>" target="_blank" rel="noopener">
                            <?php echo esc_html($repo_data['full_name']); ?>
                        </a>
                    </h3>
                    <?php if ($repo_data['language']): ?>
                        <span class="git-embed-language"><?php echo esc_html($repo_data['language']); ?></span>
                    <?php endif; ?>
                </div>
                
                <?php if ($show_description && $repo_data['description']): ?>
                    <p class="git-embed-description"><?php echo esc_html($repo_data['description']); ?></p>
                <?php endif; ?>
                
                <?php if ($show_stats): ?>
                    <div class="git-embed-stats">
                        <span class="git-embed-stat">
                            <span class="git-embed-icon">⭐</span>
                            <?php echo number_format_i18n($repo_data['stargazers_count']); ?>
                        </span>
                        <span class="git-embed-stat">
                            <span class="git-embed-icon">🍴</span>
                            <?php echo number_format_i18n($repo_data['forks_count']); ?>
                        </span>
                        <span class="git-embed-stat">
                            <span class="git-embed-icon">📝</span>
                            <?php echo number_format_i18n($repo_data['open_issues_count']); ?>
                        </span>
                    </div>
                <?php endif; ?>
                
                <?php if ($show_download): ?>
                    <div class="git-embed-actions">
                        <a href="<?php echo esc_url($repo_data['html_url']); ?>" 
                           class="git-embed-button git-embed-button-primary" 
                           target="_blank" rel="noopener">
                            View Repository
                        </a>
                        <a href="<?php echo esc_url($repo_data['clone_url']); ?>" 
                           class="git-embed-button git-embed-button-secondary">
                            Clone
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
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