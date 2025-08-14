<?php
declare(strict_types=1);

/**
 * Plugin Name: Git Embed for feiCode
 * Description: Embed Git repositories from GitHub/Gitlab/Gitea/Forgejo and Self-hosted Git service with beautiful cards
 * Version: 1.1.0
 * Author: feiCode
 * Author URI: https://feicode.com
 * Text Domain: git-embed-feicode
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if (!defined("ABSPATH")) {
    exit();
}

class GitEmbedFeiCode
{
    private const PLUGIN_VERSION = "1.1.0";
    private const BLOCK_NAME = "git-embed-feicode/repository";
    private const TEXT_DOMAIN = "git-embed-feicode";

    public function __construct()
    {
        add_action("init", [$this, "init"]);
        add_action("wp_ajax_git_embed_fetch", [$this, "ajax_fetch_repo"]);
        add_action("wp_ajax_nopriv_git_embed_fetch", [
            $this,
            "ajax_fetch_repo",
        ]);
        add_action("wp_ajax_git_embed_clear_cache", [
            $this,
            "ajax_clear_cache",
        ]);
        register_deactivation_hook(__FILE__, [$this, "clear_all_cache"]);

        add_action("plugins_loaded", [$this, "load_textdomain"]);
        add_action("wp_enqueue_scripts", [$this, "dashicons_style_front_end"]);
    }

    public function init(): void
    {
        $this->register_block();
    }

    public function load_textdomain(): void
    {
        load_plugin_textdomain(
            self::TEXT_DOMAIN,
            false,
            dirname(plugin_basename(__FILE__)) . "/languages",
        );
    }

    public function dashicons_style_front_end(): void
    {
        wp_enqueue_style('dashicons');
    }

    private function register_block(): void
    {
        register_block_type(self::BLOCK_NAME, [
            "editor_script" => "git-embed-feicode-editor",
            "editor_style" => "git-embed-feicode-style",
            "style" => "git-embed-feicode-style",
            "render_callback" => [$this, "render_block"],
            "attributes" => [
                "platform" => [
                    "type" => "string",
                    "default" => "github",
                ],
                "customDomain" => [
                    "type" => "string",
                    "default" => "",
                ],
                "customSiteName" => [
                    "type" => "string",
                    "default" => "",
                ],
                "owner" => [
                    "type" => "string",
                    "default" => "",
                ],
                "repo" => [
                    "type" => "string",
                    "default" => "",
                ],
                "showDescription" => [
                    "type" => "boolean",
                    "default" => true,
                ],
                "showStats" => [
                    "type" => "boolean",
                    "default" => true,
                ],
                "showLanguage" => [
                    "type" => "boolean",
                    "default" => true,
                ],
                "showActions" => [
                    "type" => "boolean",
                    "default" => true,
                ],
                "showViewButton" => [
                    "type" => "boolean",
                    "default" => true,
                ],
                "showCloneButton" => [
                    "type" => "boolean",
                    "default" => true,
                ],
                "showDownloadButton" => [
                    "type" => "boolean",
                    "default" => true,
                ],
                "cardStyle" => [
                    "type" => "string",
                    "default" => "default",
                ],
                "buttonStyle" => [
                    "type" => "string",
                    "default" => "default",
                ],
                "buttonSize" => [
                    "type" => "string",
                    "default" => "medium",
                ],
                "showIssuesButton" => [
                    "type" => "boolean",
                    "default" => false,
                ],
                "showForksButton" => [
                    "type" => "boolean",
                    "default" => false,
                ],
                "showAvatar" => [
                    "type" => "boolean",
                    "default" => true,
                ],
                "showSiteInfo" => [
                    "type" => "boolean",
                    "default" => true,
                ],
                "avatarSize" => [
                    "type" => "string",
                    "default" => "medium",
                ],
                "alignment" => [
                    "type" => "string",
                    "default" => "none",
                ],
            ],
        ]);

        wp_register_script(
            "git-embed-feicode-editor",
            plugin_dir_url(__FILE__) . "assets/block.js",
            [
                "wp-blocks",
                "wp-element",
                "wp-editor",
                "wp-components",
                "wp-i18n",
            ],
            self::PLUGIN_VERSION,
        );

        wp_register_style(
            "git-embed-feicode-style",
            plugin_dir_url(__FILE__) . "assets/style.css",
            [],
            self::PLUGIN_VERSION,
        );

        wp_set_script_translations(
            "git-embed-feicode-editor",
            self::TEXT_DOMAIN,
            plugin_dir_path(__FILE__) . "languages",
        );

        wp_localize_script("git-embed-feicode-editor", "gitEmbedAjax", [
            "url" => admin_url("admin-ajax.php"),
            "nonce" => wp_create_nonce("git_embed_nonce"),
            "cache_nonce" => wp_create_nonce("git_embed_cache_nonce"),
        ]);
    }

    public function render_block(array $attributes): string
    {
        $owner = sanitize_text_field($attributes["owner"] ?? "");
        $repo = sanitize_text_field($attributes["repo"] ?? "");
        $platform = sanitize_text_field($attributes["platform"] ?? "github");
        $custom_domain = sanitize_text_field($attributes["customDomain"] ?? "");
        $custom_site_name = sanitize_text_field(
            $attributes["customSiteName"] ?? "",
        );

        if (empty($owner) || empty($repo)) {
            return '<div class="git-embed-error">' .
                esc_html__(
                    "Repository information required",
                    self::TEXT_DOMAIN,
                ) .
                "</div>";
        }

        if (
            in_array($platform, ["gitea", "forgejo", "gitlab", "custom"]) &&
            empty($custom_domain)
        ) {
            return '<div class="git-embed-error">' .
                esc_html(
                    sprintf(
                        __("Custom domain required for %s", self::TEXT_DOMAIN),
                        ucfirst($platform),
                    ),
                ) .
                "</div>";
        }

        $repo_data = $this->fetch_repository_data(
            $platform,
            $owner,
            $repo,
            $custom_domain,
            $custom_site_name,
        );

        if (!$repo_data) {
            return '<div class="git-embed-error">' .
                esc_html__(
                    "Failed to fetch repository data",
                    self::TEXT_DOMAIN,
                ) .
                "</div>";
        }

        return $this->render_repository_card($repo_data, $attributes);
    }

    private function fetch_repository_data(
        string $platform,
        string $owner,
        string $repo,
        string $custom_domain = "",
        string $custom_site_name = "",
    ): ?array {
        $cache_key = "git_embed_{$platform}_{$owner}_{$repo}" .
            ($custom_domain ? "_{$custom_domain}" : "") .
            ($custom_site_name ? "_{$custom_site_name}" : "");
        
        $cached = get_transient($cache_key);
        if ($cached !== false) {
            return $cached;
        }

        $api_config = $this->get_api_config($platform, $custom_domain, $custom_site_name);
        if (!$api_config) {
            $this->log_debug("Failed to get API config for platform: {$platform}");
            return null;
        }

        $repo_data = $this->fetch_repository_info($api_config, $owner, $repo, $platform);
        if (!$repo_data) {
            return null;
        }

        $latest_release = $this->fetch_latest_release($api_config, $owner, $repo, $platform);
        
        $repo_data["download_info"] = $this->generate_download_info(
            $platform, 
            $api_config, 
            $owner, 
            $repo, 
            $repo_data,
            $latest_release
        );

        $repo_data["archive_url"] = $repo_data["download_info"]["url"];

        $this->log_debug("Platform: {$platform}");
        $this->log_debug("API URL: " . $api_config["api_url"]);
        $this->log_debug("Base URL: " . $api_config["base_url"]);
        $this->log_debug("Final download URL: " . $repo_data["archive_url"]);
        $this->log_debug("Download filename: " . $repo_data["download_info"]["filename"]);

        set_transient($cache_key, $repo_data, DAY_IN_SECONDS);

        if (!empty($repo_data["repo_avatar_url"])) {
            $this->cache_avatar($repo_data["repo_avatar_url"]);
        } elseif (!empty($repo_data["owner"]["avatar_url"])) {
            $this->cache_avatar($repo_data["owner"]["avatar_url"]);
        }

        return $repo_data;
    }

    private function fetch_repository_info(array $api_config, string $owner, string $repo, string $platform): ?array
    {
        $url = $api_config["api_url"] . "/repos/{$owner}/{$repo}";
        
        $this->log_debug("Fetching repo info from: {$url}");
        
        $response = wp_remote_get($url, [
            "timeout" => 15,
            "headers" => [
                "User-Agent" => "Git-Embed-FeiCode/" . self::PLUGIN_VERSION,
                "Accept" => "application/json",
            ],
        ]);

        if (is_wp_error($response)) {
            $this->log_debug("WP Error: " . $response->get_error_message());
            return null;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            $this->log_debug("HTTP {$response_code} error");
            return null;
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);
        if (!$data) {
            $this->log_debug("Failed to parse JSON response");
            return null;
        }

        return $this->normalize_repository_data($data, $platform, $api_config);
    }

    private function fetch_latest_release(array $api_config, string $owner, string $repo, string $platform): ?array
    {
        if ($platform === "gitlab") {
            $releases_url = $api_config["api_url"] . "/projects/" . urlencode("{$owner}/{$repo}") . "/releases";
        } else {
            $releases_url = $api_config["api_url"] . "/repos/{$owner}/{$repo}/releases/latest";
        }

        $this->log_debug("Fetching latest release from: {$releases_url}");

        $response = wp_remote_get($releases_url, [
            "timeout" => 10,
            "headers" => [
                "User-Agent" => "Git-Embed-FeiCode/" . self::PLUGIN_VERSION,
                "Accept" => "application/json",
            ],
        ]);

        if (is_wp_error($response)) {
            $this->log_debug("Release fetch error: " . $response->get_error_message());
            return null;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code === 404) {
            $this->log_debug("No releases found (404)");
            return null;
        }

        if ($response_code !== 200) {
            $this->log_debug("Release API returned {$response_code}");
            return null;
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);
        if (!$data) {
            return null;
        }

        if ($platform === "gitlab") {
            if (is_array($data) && !empty($data)) {
                $latest = $data[0];
                return [
                    "tag_name" => $latest["tag_name"],
                    "name" => $latest["name"] ?: $latest["tag_name"],
                ];
            }
            return null;
        }

        if (isset($data["tag_name"])) {
            return [
                "tag_name" => $data["tag_name"],
                "name" => $data["name"] ?: $data["tag_name"],
                "draft" => $data["draft"] ?? false,
            ];
        }

        return null;
    }

    private function generate_download_info(
        string $platform, 
        array $api_config, 
        string $owner, 
        string $repo, 
        array $repo_data,
        ?array $latest_release
    ): array {
        if ($latest_release && (!isset($latest_release["draft"]) || !$latest_release["draft"])) {
            $tag_name = $latest_release["tag_name"];
            $this->log_debug("Using release {$tag_name} for download");
            
            return [
                "type" => "release",
                "version" => $tag_name,
                "name" => $latest_release["name"] ?: $tag_name,
                "url" => $this->build_archive_url($platform, $api_config, $owner, $repo, $tag_name, true),
                "filename" => $this->generate_filename($repo_data["name"], $tag_name, true)
            ];
        }

        $default_branch = $repo_data["default_branch"] ?: $this->detect_default_branch($api_config, $owner, $repo, $platform);
        $this->log_debug("Using branch '{$default_branch}' for download");
        
        return [
            "type" => "branch",
            "version" => $default_branch,
            "name" => __("Latest Code", self::TEXT_DOMAIN),
            "url" => $this->build_archive_url($platform, $api_config, $owner, $repo, $default_branch, false),
            "filename" => $this->generate_filename($repo_data["name"], $default_branch, false)
        ];
    }

    private function build_archive_url(
        string $platform,
        array $api_config,
        string $owner,
        string $repo,
        string $ref,
        bool $is_release
    ): string {
        $base_url = $api_config["base_url"];
        $api_url = $api_config["api_url"];
        
        switch ($platform) {
            case "github":
                $prefix = $is_release ? "refs/tags" : "refs/heads";
                return "https://github.com/{$owner}/{$repo}/archive/{$prefix}/{$ref}.zip";

            case "gitlab":
                return "{$base_url}/{$owner}/{$repo}/-/archive/{$ref}/{$repo}-{$ref}.zip";

            case "gitea":
            case "forgejo":
            case "custom":
                return "{$api_url}/repos/{$owner}/{$repo}/archive/{$ref}.zip";

            default:
                return "";
        }
    }

    private function detect_default_branch(array $api_config, string $owner, string $repo, string $platform): string
    {
        $branches_url = $api_config["api_url"] . "/repos/{$owner}/{$repo}/branches";
        
        $response = wp_remote_get($branches_url, [
            "timeout" => 10,
            "headers" => [
                "User-Agent" => "Git-Embed-FeiCode/" . self::PLUGIN_VERSION,
                "Accept" => "application/json",
            ],
        ]);

        if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
            $branches = json_decode(wp_remote_retrieve_body($response), true);
            if (is_array($branches) && !empty($branches)) {
                foreach ($branches as $branch) {
                    if (isset($branch["name"]) && in_array($branch["name"], ["main", "master"])) {
                        return $branch["name"];
                    }
                }
                if (isset($branches[0]["name"])) {
                    return $branches[0]["name"];
                }
            }
        }

        return "main";
    }

    private function generate_filename(string $repo_name, string $ref, bool $is_release): string
    {
        $safe_repo = preg_replace('/[^a-zA-Z0-9\-_.]/', '-', $repo_name);
        
        $clean_ref = $ref;
        if (strpos($ref, 'refs/heads/') === 0) {
            $clean_ref = substr($ref, 11);
        } elseif (strpos($ref, 'refs/tags/') === 0) {
            $clean_ref = substr($ref, 10);
        }
        
        $safe_ref = preg_replace('/[^a-zA-Z0-9\-_.]/', '-', $clean_ref);
        
        if ($is_release) {
            $safe_ref = preg_replace('/^v/', '', $safe_ref);
        }
        
        return "{$safe_repo}-{$safe_ref}.zip";
    }

    private function log_debug(string $message): void
    {
        if (WP_DEBUG) {
            error_log("Git Embed Debug: {$message}");
        }
    }

    private function get_api_config(
        string $platform,
        string $custom_domain = "",
        string $custom_site_name = "",
    ): ?array {
        switch ($platform) {
            case "github":
                return [
                    "api_url" => "https://api.github.com",
                    "base_url" => "https://github.com",
                    "site_info" => [
                        "name" => "GitHub",
                        "url" => "https://github.com",
                        "favicon" => "https://cn.cravatar.com/favicon/api/index.php?url=github.com",
                        "color" => "#24292f",
                    ],
                ];

            case "gitea":
                if (empty($custom_domain)) {
                    return null;
                }
                $domain = $this->normalize_domain($custom_domain);
                $site_name = $custom_site_name ?: $this->get_site_name($domain, "Gitea");
                return [
                    "api_url" => "https://{$domain}/api/v1",
                    "base_url" => "https://{$domain}",
                    "site_info" => [
                        "name" => $site_name,
                        "url" => "https://{$domain}",
                        "favicon" => "https://cn.cravatar.com/favicon/api/index.php?url={$domain}",
                        "color" => "#609926",
                    ],
                ];

            case "forgejo":
                if (empty($custom_domain)) {
                    return null;
                }
                $domain = $this->normalize_domain($custom_domain);
                $site_name = $custom_site_name ?: $this->get_site_name($domain, "Forgejo");
                return [
                    "api_url" => "https://{$domain}/api/v1",
                    "base_url" => "https://{$domain}",
                    "site_info" => [
                        "name" => $site_name,
                        "url" => "https://{$domain}",
                        "favicon" => "https://cn.cravatar.com/favicon/api/index.php?url={$domain}",
                        "color" => "#fb923c",
                    ],
                ];

            case "gitlab":
                if (empty($custom_domain)) {
                    return null;
                }
                $domain = $this->normalize_domain($custom_domain);
                $site_name = $custom_site_name ?: $this->get_site_name($domain, "GitLab");
                return [
                    "api_url" => "https://{$domain}/api/v4",
                    "base_url" => "https://{$domain}",
                    "site_info" => [
                        "name" => $site_name,
                        "url" => "https://{$domain}",
                        "favicon" => "https://cn.cravatar.com/favicon/api/index.php?url={$domain}",
                        "color" => "#fc6d26",
                    ],
                ];

            case "custom":
                if (empty($custom_domain)) {
                    return null;
                }
                $domain = $this->normalize_domain($custom_domain);
                $site_name = $custom_site_name ?: $this->get_site_name($domain, __("Git Service", self::TEXT_DOMAIN));
                return [
                    "api_url" => "https://{$domain}/api/v1",
                    "base_url" => "https://{$domain}",
                    "site_info" => [
                        "name" => $site_name,
                        "url" => "https://{$domain}",
                        "favicon" => "https://cn.cravatar.com/favicon/api/index.php?url={$domain}",
                        "color" => "#6366f1",
                    ],
                ];

            default:
                return null;
        }
    }

    private function get_site_name(string $domain, string $fallback): string
    {
        $cache_key = "git_embed_site_name_" . md5($domain);
        $cached = get_transient($cache_key);

        if ($cached !== false) {
            return $cached;
        }

        $response = wp_remote_get("https://{$domain}", [
            "timeout" => 10,
            "headers" => [
                "User-Agent" => "Git-Embed-FeiCode/" . self::PLUGIN_VERSION,
            ],
        ]);

        if (
            !is_wp_error($response) &&
            wp_remote_retrieve_response_code($response) === 200
        ) {
            $body = wp_remote_retrieve_body($response);
            if (preg_match("/<title[^>]*>([^<]+)<\/title>/i", $body, $matches)) {
                $title = trim(html_entity_decode($matches[1], ENT_QUOTES, "UTF-8"));
                if (!empty($title) && strlen($title) < 100) {
                    set_transient($cache_key, $title, DAY_IN_SECONDS);
                    return $title;
                }
            }
        }

        set_transient($cache_key, $fallback, HOUR_IN_SECONDS);
        return $fallback;
    }

    private function normalize_domain(string $domain): string
    {
        $domain = trim($domain);
        $domain = preg_replace("/^https?:\/\//", "", $domain);
        $domain = rtrim($domain, "/");
        return $domain;
    }

    private function normalize_repository_data(array $data, string $platform, array $api_config): array
    {
        $base_url = $api_config["base_url"];

        if ($platform === "gitlab") {
            return [
                "name" => $data["name"],
                "full_name" => $data["path_with_namespace"] ?? ($data["namespace"]["name"] . "/" . $data["name"]),
                "description" => $data["description"],
                "html_url" => $data["web_url"],
                "language" => $data["language"] ?? null,
                "stargazers_count" => $data["star_count"] ?? 0,
                "forks_count" => $data["forks_count"] ?? 0,
                "open_issues_count" => $data["open_issues_count"] ?? 0,
                "clone_url" => $data["http_url_to_repo"],
                "default_branch" => $data["default_branch"] ?? null,
                "repo_avatar_url" => $data["avatar_url"] ?? null,
                "owner" => [
                    "login" => $data["namespace"]["name"] ?? $data["owner"]["username"],
                    "avatar_url" => $data["namespace"]["avatar_url"] ?? $data["owner"]["avatar_url"],
                    "html_url" => $base_url . "/" . ($data["namespace"]["name"] ?? $data["owner"]["username"]),
                    "type" => $this->normalize_owner_type($data["namespace"]["kind"] ?? ($data["owner"]["type"] ?? "user")),
                ],
                "site_info" => $api_config["site_info"],
                "platform" => $platform,
            ];
        }

        return [
            "name" => $data["name"],
            "full_name" => $data["full_name"] ?? ($data["owner"]["login"] . "/" . $data["name"]),
            "description" => $data["description"],
            "html_url" => $data["html_url"],
            "language" => $data["language"],
            "stargazers_count" => $data["stargazers_count"] ?? ($data["stars_count"] ?? 0),
            "forks_count" => $data["forks_count"] ?? ($data["forks"] ?? 0),
            "open_issues_count" => $data["open_issues_count"] ?? ($data["open_issues"] ?? 0),
            "clone_url" => $data["clone_url"],
            "default_branch" => $data["default_branch"] ?? null,
            "repo_avatar_url" => $data["avatar_url"] ?? null,
            "owner" => [
                "login" => $data["owner"]["login"],
                "avatar_url" => $data["owner"]["avatar_url"],
                "html_url" => $data["owner"]["html_url"] ?? ($base_url . "/" . $data["owner"]["login"]),
                "type" => $this->normalize_owner_type($this->detect_owner_type($data["owner"], $platform)),
            ],
            "site_info" => $api_config["site_info"],
            "platform" => $platform,
        ];
    }

    private function normalize_owner_type(string $type): string
    {
        $type = strtolower(trim($type));
        switch ($type) {
            case "organization":
            case "org":
            case "group":
            case "team":
                return __("Organization", self::TEXT_DOMAIN);
            case "user":
            case "individual":
            case "person":
            default:
                return __("User", self::TEXT_DOMAIN);
        }
    }

    private function detect_owner_type(array $owner_data, string $platform = ""): string
    {
        // 如果有明确的 type 字段，直接使用
        if (isset($owner_data["type"]) && !empty($owner_data["type"])) {
            return $owner_data["type"];
        }
        
        // 对于 Gitea/Forgejo 等平台，默认显示为组织
        if (in_array($platform, ["gitea", "forgejo", "custom"])) {
            return "organization";
        }
        
        // 默认为用户
        return "user";
    }

    private function cache_avatar(string $avatar_url): void
    {
        if (empty($avatar_url)) {
            return;
        }

        $cache_key = "git_embed_avatar_" . md5($avatar_url);

        if (get_transient($cache_key) !== false) {
            return;
        }

        $response = wp_remote_get($avatar_url, [
            "timeout" => 10,
            "headers" => [
                "User-Agent" => "Git-Embed-FeiCode/" . self::PLUGIN_VERSION,
            ],
        ]);

        if (
            !is_wp_error($response) &&
            wp_remote_retrieve_response_code($response) === 200
        ) {
            set_transient($cache_key, true, WEEK_IN_SECONDS);
        }
    }

    private function get_display_avatar_url(array $repo_data): string
    {
        if (!empty($repo_data["repo_avatar_url"])) {
            return $repo_data["repo_avatar_url"];
        }

        if (!empty($repo_data["owner"]["avatar_url"])) {
            return $repo_data["owner"]["avatar_url"];
        }

        return "";
    }

    private function render_repository_card(array $repo_data, array $attributes): string
    {
        $show_description = $attributes["showDescription"] ?? true;
        $show_stats = $attributes["showStats"] ?? true;
        $show_language = $attributes["showLanguage"] ?? true;
        $show_actions = $attributes["showActions"] ?? true;
        $show_view_button = $attributes["showViewButton"] ?? true;
        $show_clone_button = $attributes["showCloneButton"] ?? true;
        $show_download_button = $attributes["showDownloadButton"] ?? true;
        $show_issues_button = $attributes["showIssuesButton"] ?? false;
        $show_forks_button = $attributes["showForksButton"] ?? false;
        $show_avatar = $attributes["showAvatar"] ?? true;
        $show_site_info = $attributes["showSiteInfo"] ?? true;
        $avatar_size = $attributes["avatarSize"] ?? "medium";
        $card_style = $attributes["cardStyle"] ?? "default";
        $button_style = $attributes["buttonStyle"] ?? "default";
        $button_size = $attributes["buttonSize"] ?? "medium";
        $alignment = $attributes["alignment"] ?? "none";

        $align_class = $alignment !== "none" ? " align{$alignment}" : "";
        $card_class = $card_style !== "default" ? " git-embed-card-{$card_style}" : "";
        $avatar_class = "git-embed-avatar-{$avatar_size}";
        $button_class = "git-embed-button-{$button_size}";

        $download_info = $repo_data["download_info"] ?? null;
        $display_avatar_url = $this->get_display_avatar_url($repo_data);

        $download_url = '';
        $download_filename = '';
        $download_title = '';

        if ($download_info) {
            $download_url = $download_info["url"];
            $download_filename = $download_info["filename"];
            $download_title = sprintf(
                __("Download %s (%s)", self::TEXT_DOMAIN),
                $download_info["name"],
                $download_info["version"]
            );
        } elseif (!empty($repo_data["archive_url"])) {
            $download_url = $repo_data["archive_url"];
            $download_filename = $repo_data["name"] . ".zip";
            $download_title = __("Download ZIP", self::TEXT_DOMAIN);
        }

        $unique_id = 'git-embed-' . wp_generate_uuid4();

        ob_start();
        ?>
        <div class="wp-block-git-embed-feicode-repository<?php echo esc_attr($align_class); ?>" id="<?php echo esc_attr($unique_id); ?>">
            <div class="git-embed-card<?php echo esc_attr($card_class); ?>">
                <?php if ($show_site_info): ?>
                    <div class="git-embed-site-info platform-<?php echo esc_attr($repo_data["platform"] ?? "github"); ?>">
                        <img src="<?php echo esc_url($repo_data["site_info"]["favicon"]); ?>"
                             alt="<?php echo esc_attr($repo_data["site_info"]["name"]); ?>"
                             class="git-embed-site-favicon"
                             loading="lazy"
                             onerror="this.style.display='none'">
                        <span class="git-embed-site-name">
                            <a href="<?php echo esc_url($repo_data["site_info"]["url"]); ?>"
                               target="_blank" rel="noopener">
                                <?php echo esc_html($repo_data["site_info"]["name"]); ?>
                            </a>
                        </span>
                    </div>
                <?php endif; ?>

                <div class="git-embed-header">
                    <div class="git-embed-title-section">
                        <?php if ($show_avatar && $display_avatar_url): ?>
                            <img src="<?php echo esc_url($display_avatar_url); ?>"
                                 alt="<?php echo esc_attr($repo_data["name"]); ?>"
                                 class="git-embed-avatar <?php echo esc_attr($avatar_class); ?>"
                                 loading="lazy"
                                 title="<?php echo !empty($repo_data["repo_avatar_url"])
                                     ? esc_attr__("Repository Avatar", self::TEXT_DOMAIN)
                                     : esc_attr__("Owner Avatar", self::TEXT_DOMAIN); ?>">
                        <?php endif; ?>

                        <div class="git-embed-title-content">
                            <h3 class="git-embed-title">
                                <span class="dashicons dashicons-embed-generic git-embed-repo-icon"></span>
                                <a href="<?php echo esc_url($repo_data["html_url"]); ?>" target="_blank" rel="noopener">
                                    <?php echo esc_html($repo_data["full_name"]); ?>
                                </a>
                            </h3>

                            <?php if ($show_avatar): ?>
                                <div class="git-embed-owner-info">
                                    <?php if (!empty($repo_data["owner"]["type"])): ?>
                                        <span class="git-embed-owner-type"><?php echo esc_html($repo_data["owner"]["type"]); ?></span>
                                    <?php endif; ?>
                                    <a href="<?php echo esc_url($repo_data["owner"]["html_url"]); ?>"
                                       target="_blank" rel="noopener" class="git-embed-owner-link">
                                        @<?php echo esc_html($repo_data["owner"]["login"]); ?>
                                    </a>
                                    <?php if (!empty($repo_data["repo_avatar_url"])): ?>
                                        <span class="git-embed-repo-avatar-badge"
                                              title="<?php echo esc_attr__("Repository has custom avatar", self::TEXT_DOMAIN); ?>">
                                        </span>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="git-embed-meta-section">
                        <?php if ($show_language && $repo_data["language"]): ?>
                            <span class="git-embed-language">
                                <span class="dashicons dashicons-editor-code"></span>
                                <?php echo esc_html($repo_data["language"]); ?>
                            </span>
                        <?php endif; ?>

                        <span class="git-embed-platform-badge platform-<?php echo esc_attr($repo_data["platform"] ?? "github"); ?>">
                            <?php echo esc_html(strtoupper($repo_data["platform"] ?? "github")); ?>
                        </span>
                    </div>
                </div>

                <?php if ($show_description && $repo_data["description"]): ?>
                    <p class="git-embed-description">
                        <span class="dashicons dashicons-editor-quote"></span>
                        <?php echo esc_html($repo_data["description"]); ?>
                    </p>
                <?php endif; ?>

                <?php if ($show_stats): ?>
                    <div class="git-embed-stats">
                        <span class="git-embed-stat">
                            <span class="dashicons dashicons-star-filled"></span>
                            <span class="git-embed-stat-label"><?php echo esc_html__("Stars:", self::TEXT_DOMAIN); ?></span>
                            <span class="git-embed-stat-value"><?php echo number_format_i18n($repo_data["stargazers_count"]); ?></span>
                        </span>
                        <span class="git-embed-stat">
                            <span class="dashicons dashicons-share"></span>
                            <span class="git-embed-stat-label"><?php echo esc_html__("Forks:", self::TEXT_DOMAIN); ?></span>
                            <span class="git-embed-stat-value"><?php echo number_format_i18n($repo_data["forks_count"]); ?></span>
                        </span>
                        <span class="git-embed-stat">
                            <span class="dashicons dashicons-editor-help"></span>
                            <span class="git-embed-stat-label"><?php echo esc_html__("Issues:", self::TEXT_DOMAIN); ?></span>
                            <span class="git-embed-stat-value"><?php echo number_format_i18n($repo_data["open_issues_count"]); ?></span>
                        </span>
                    </div>
                <?php endif; ?>

                <?php if ($show_actions && ($show_view_button || $show_clone_button || $show_download_button || $show_issues_button || $show_forks_button)): ?>
                    <div class="git-embed-actions">
                        <?php if ($show_view_button): ?>
                            <a href="<?php echo esc_url($repo_data["html_url"]); ?>"
                               class="git-embed-button git-embed-button-<?php echo esc_attr($button_style); ?> <?php echo esc_attr($button_class); ?>"
                               target="_blank" rel="noopener">
                                <span class="dashicons dashicons-external"></span>
                                <?php echo esc_html__("View Repository", self::TEXT_DOMAIN); ?>
                            </a>
                        <?php endif; ?>

                        <?php if ($show_clone_button): ?>
                            <button type="button"
                                    class="git-embed-button git-embed-button-secondary <?php echo esc_attr($button_class); ?> git-embed-clone-btn"
                                    data-clone-url="<?php echo esc_attr($repo_data["clone_url"]); ?>"
                                    data-container="<?php echo esc_attr($unique_id); ?>"
                                    title="<?php echo esc_attr__("Click to copy clone URL", self::TEXT_DOMAIN); ?>">
                                <span class="dashicons dashicons-admin-page"></span>
                                <?php echo esc_html__("Clone", self::TEXT_DOMAIN); ?>
                            </button>
                        <?php endif; ?>

                        <?php if ($show_download_button && !empty($download_url)): ?>
                            <a href="<?php echo esc_url($download_url); ?>"
                               class="git-embed-button git-embed-button-secondary <?php echo esc_attr($button_class); ?> git-embed-download-btn"
                               data-filename="<?php echo esc_attr($download_filename); ?>"
                               data-container="<?php echo esc_attr($unique_id); ?>"
                               title="<?php echo esc_attr($download_title); ?>">
                                <span class="dashicons dashicons-download"></span>
                                <?php if ($download_info && $download_info["type"] === "release"): ?>
                                    <?php echo esc_html(sprintf(__("Download %s", self::TEXT_DOMAIN), $download_info["version"])); ?>
                                <?php else: ?>
                                    <?php echo esc_html__("Download ZIP", self::TEXT_DOMAIN); ?>
                                <?php endif; ?>
                            </a>
                        <?php endif; ?>

                        <?php if ($show_issues_button): ?>
                            <a href="<?php echo esc_url($repo_data["html_url"] . "/issues"); ?>"
                               class="git-embed-button git-embed-button-outline <?php echo esc_attr($button_class); ?>"
                               target="_blank" rel="noopener">
                                <span class="dashicons dashicons-editor-help"></span>
                                <?php echo esc_html(sprintf(__("Issues (%s)", self::TEXT_DOMAIN), number_format_i18n($repo_data["open_issues_count"]))); ?>
                            </a>
                        <?php endif; ?>

                        <?php if ($show_forks_button): ?>
                            <a href="<?php echo esc_url($repo_data["html_url"] . "/forks"); ?>"
                               class="git-embed-button git-embed-button-outline <?php echo esc_attr($button_class); ?>"
                               target="_blank" rel="noopener">
                                <span class="dashicons dashicons-networking"></span>
                                <?php echo esc_html(sprintf(__("Forks (%s)", self::TEXT_DOMAIN), number_format_i18n($repo_data["forks_count"]))); ?>
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>

            <?php if ($show_clone_button || $show_download_button): ?>
            <script type="text/javascript">
            (function() {
                'use strict';
                
                const containerId = '<?php echo esc_js($unique_id); ?>';
                const container = document.getElementById(containerId);
                if (!container) return;

                const cloneBtn = container.querySelector('.git-embed-clone-btn');
                if (cloneBtn && !cloneBtn.hasAttribute('data-initialized')) {
                    cloneBtn.setAttribute('data-initialized', 'true');
                    cloneBtn.addEventListener('click', function() {
                        const cloneUrl = this.getAttribute('data-clone-url');
                        const originalHtml = this.innerHTML;
                        
                        if (navigator.clipboard && navigator.clipboard.writeText) {
                            navigator.clipboard.writeText(cloneUrl).then(() => {
                                this.innerHTML = '<span class="dashicons dashicons-yes"></span><?php echo esc_js(__("Copied!", self::TEXT_DOMAIN)); ?>';
                                setTimeout(() => {
                                    this.innerHTML = originalHtml;
                                }, 2000);
                            }).catch(() => {
                                fallbackCopy(cloneUrl, this, originalHtml);
                            });
                        } else {
                            fallbackCopy(cloneUrl, this, originalHtml);
                        }
                    });
                }

                const downloadBtn = container.querySelector('.git-embed-download-btn');
                if (downloadBtn && !downloadBtn.hasAttribute('data-initialized')) {
                    downloadBtn.setAttribute('data-initialized', 'true');
                    downloadBtn.addEventListener('click', function(e) {
                        const filename = this.getAttribute('data-filename');
                        if (filename) {
                            e.preventDefault();
                            const url = this.href;
                            
                            const a = document.createElement('a');
                            a.href = url;
                            a.download = filename;
                            a.style.display = 'none';
                            document.body.appendChild(a);
                            a.click();
                            document.body.removeChild(a);
                        }
                    });
                }

                function fallbackCopy(text, button, originalHtml) {
                    const textarea = document.createElement('textarea');
                    textarea.value = text;
                    textarea.style.position = 'fixed';
                    textarea.style.opacity = '0';
                    document.body.appendChild(textarea);
                    textarea.select();
                    
                    try {
                        document.execCommand('copy');
                        button.innerHTML = '<span class="dashicons dashicons-yes"></span><?php echo esc_js(__("Copied!", self::TEXT_DOMAIN)); ?>';
                        setTimeout(() => {
                            button.innerHTML = originalHtml;
                        }, 2000);
                    } catch(err) {
                        console.error('Copy failed:', err);
                    }
                    
                    document.body.removeChild(textarea);
                }
            })();
            </script>
            <?php endif; ?>
        </div>
        <?php 
        return ob_get_clean();
    }

    public function ajax_fetch_repo(): void
    {
        check_ajax_referer("git_embed_nonce", "nonce");

        $platform = sanitize_text_field($_POST["platform"] ?? "");
        $custom_domain = sanitize_text_field($_POST["customDomain"] ?? "");
        $custom_site_name = sanitize_text_field($_POST["customSiteName"] ?? "");
        $owner = sanitize_text_field($_POST["owner"] ?? "");
        $repo = sanitize_text_field($_POST["repo"] ?? "");

        if (empty($owner) || empty($repo)) {
            wp_send_json_error(__("Please enter repository owner and name", self::TEXT_DOMAIN));
        }

        if (in_array($platform, ["gitea", "forgejo", "gitlab", "custom"]) && empty($custom_domain)) {
            wp_send_json_error(sprintf(__("Please enter custom domain for %s", self::TEXT_DOMAIN), ucfirst($platform)));
        }

        $repo_data = $this->fetch_repository_data($platform, $owner, $repo, $custom_domain, $custom_site_name);

        if (!$repo_data) {
            wp_send_json_error(__("Failed to fetch repository", self::TEXT_DOMAIN));
        }

        wp_send_json_success($repo_data);
    }

    public function ajax_clear_cache(): void
    {
        check_ajax_referer("git_embed_cache_nonce", "nonce");

        if (!current_user_can("manage_options")) {
            wp_send_json_error(__("Insufficient permissions", self::TEXT_DOMAIN));
        }

        $platform = sanitize_text_field($_POST["platform"] ?? "");
        $custom_domain = sanitize_text_field($_POST["customDomain"] ?? "");
        $custom_site_name = sanitize_text_field($_POST["customSiteName"] ?? "");
        $owner = sanitize_text_field($_POST["owner"] ?? "");
        $repo = sanitize_text_field($_POST["repo"] ?? "");

        if (empty($owner) || empty($repo)) {
            wp_send_json_error(__("Repository information required", self::TEXT_DOMAIN));
        }

        $this->clear_repository_cache($platform, $owner, $repo, $custom_domain, $custom_site_name);

        wp_send_json_success(__("Cache cleared successfully", self::TEXT_DOMAIN));
    }

    private function clear_repository_cache(
        string $platform,
        string $owner,
        string $repo,
        string $custom_domain = "",
        string $custom_site_name = "",
    ): void {
        $cache_key = "git_embed_{$platform}_{$owner}_{$repo}" .
            ($custom_domain ? "_{$custom_domain}" : "") .
            ($custom_site_name ? "_{$custom_site_name}" : "");
        delete_transient($cache_key);

        if ($custom_domain) {
            $site_cache_key = "git_embed_site_name_" . md5($custom_domain);
            delete_transient($site_cache_key);
        }

        global $wpdb;
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                $wpdb->esc_like("_transient_git_embed_avatar_") . "%",
            ),
        );
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                $wpdb->esc_like("_transient_timeout_git_embed_avatar_") . "%",
            ),
        );
    }

    public function clear_all_cache(): void
    {
        global $wpdb;
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                $wpdb->esc_like("_transient_git_embed_") . "%",
            ),
        );
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                $wpdb->esc_like("_transient_timeout_git_embed_") . "%",
            ),
        );
    }
}

new GitEmbedFeiCode();