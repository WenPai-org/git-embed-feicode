(function() {
    const { registerBlockType } = wp.blocks;
    const { createElement: el, useState, useEffect } = wp.element;
    const { 
        InspectorControls, 
        BlockControls,
        BlockAlignmentToolbar,
        useBlockProps 
    } = wp.blockEditor;
    const { 
        PanelBody, 
        TextControl, 
        ToggleControl, 
        SelectControl,
        Button,
        Spinner,
        Notice
    } = wp.components;
    const { __ } = wp.i18n;

    registerBlockType('git-embed-feicode/repository', {
        title: __('Git Repository', 'git-embed-feicode'),
        description: __('Embed a Git repository with information and stats', 'git-embed-feicode'),
        icon: 'embed-generic',
        category: 'embed',
        supports: {
            align: ['left', 'center', 'right', 'wide', 'full']
        },
        attributes: {
            platform: {
                type: 'string',
                default: 'github'
            },
            customDomain: {
                type: 'string',
                default: ''
            },
            customSiteName: {
                type: 'string',
                default: ''
            },
            owner: {
                type: 'string',
                default: ''
            },
            repo: {
                type: 'string',
                default: ''
            },
            showDescription: {
                type: 'boolean',
                default: true
            },
            showStats: {
                type: 'boolean',
                default: true
            },
            showLanguage: {
                type: 'boolean',
                default: true
            },
            showActions: {
                type: 'boolean',
                default: true
            },
            showViewButton: {
                type: 'boolean',
                default: true
            },
            showCloneButton: {
                type: 'boolean',
                default: true
            },
            showDownloadButton: {
                type: 'boolean',
                default: true
            },
            showAvatar: {
                type: 'boolean',
                default: true
            },
            showSiteInfo: {
                type: 'boolean',
                default: true
            },
            avatarSize: {
                type: 'string',
                default: 'medium'
            },
            cardStyle: {
                type: 'string',
                default: 'default'
            },
            buttonStyle: {
                type: 'string',
                default: 'default'
            },
            buttonSize: {
                type: 'string',
                default: 'medium'
            },
            showIssuesButton: {
                type: 'boolean',
                default: false
            },
            showForksButton: {
                type: 'boolean',
                default: false
            },
            alignment: {
                type: 'string',
                default: 'none'
            }
        },

        edit: function(props) {
            const { attributes, setAttributes } = props;
            const { 
                platform, customDomain, customSiteName, owner, repo, showDescription, showStats, showLanguage,
                showActions, showViewButton, showCloneButton, showDownloadButton, showIssuesButton, showForksButton,
                showAvatar, showSiteInfo, avatarSize, cardStyle, buttonStyle, buttonSize, alignment 
            } = attributes;
            
            const [repoData, setRepoData] = useState(null);
            const [loading, setLoading] = useState(false);
            const [error, setError] = useState('');

            const blockProps = useBlockProps({
                className: alignment !== 'none' ? `align${alignment}` : ''
            });

            const fetchRepoData = () => {
                if (!owner || !repo) {
                    setError(__('Please enter repository owner and name', 'git-embed-feicode'));
                    return;
                }

                if ((platform === 'gitea' || platform === 'forgejo' || platform === 'gitlab' || platform === 'custom') && !customDomain) {
                    setError(__('Please enter custom domain for', 'git-embed-feicode') + ' ' + platform.charAt(0).toUpperCase() + platform.slice(1));
                    return;
                }

                setLoading(true);
                setError('');

                const formData = new FormData();
                formData.append('action', 'git_embed_fetch');
                formData.append('platform', platform);
                formData.append('customDomain', customDomain);
                formData.append('customSiteName', customSiteName);
                formData.append('owner', owner);
                formData.append('repo', repo);
                formData.append('nonce', gitEmbedAjax.nonce);

                fetch(gitEmbedAjax.url, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    setLoading(false);
                    if (data.success) {
                        setRepoData(data.data);
                        setError('');
                    } else {
                        setError(data.data || __('Failed to fetch repository', 'git-embed-feicode'));
                        setRepoData(null);
                    }
                })
                .catch(err => {
                    setLoading(false);
                    setError(__('Network error occurred', 'git-embed-feicode'));
                    setRepoData(null);
                });
            };

            useEffect(() => {
                if (owner && repo && (platform === 'github' || (platform !== 'github' && customDomain))) {
                    fetchRepoData();
                }
            }, [owner, repo, platform, customDomain, customSiteName]);

            const getDisplayAvatarUrl = (repoData) => {
                if (repoData.repo_avatar_url) {
                    return repoData.repo_avatar_url;
                }
                if (repoData.owner && repoData.owner.avatar_url) {
                    return repoData.owner.avatar_url;
                }
                return '';
            };

            const handleDownload = (url, filename) => {
                window.open(url, '_blank');
            };

            const renderPreview = () => {
                if (loading) {
                    return el('div', { className: 'git-embed-loading' },
                        el(Spinner),
                        el('p', null, __('Fetching repository data...', 'git-embed-feicode'))
                    );
                }

                if (error) {
                    return el(Notice, {
                        status: 'error',
                        isDismissible: false
                    }, error);
                }

                if (!repoData) {
                    return el('div', { className: 'git-embed-placeholder' },
                        el('div', { className: 'git-embed-placeholder-content' },
                            el('span', { className: 'dashicons dashicons-embed-generic git-embed-placeholder-icon' }),
                            el('h3', null, __('Git Repository Embed', 'git-embed-feicode')),
                            el('p', null, __('Configure your repository details in the sidebar', 'git-embed-feicode'))
                        )
                    );
                }

                const cardClass = `git-embed-card${cardStyle !== 'default' ? ` git-embed-card-${cardStyle}` : ''}`;
                const avatarClass = `git-embed-avatar git-embed-avatar-${avatarSize}`;
                const buttonClass = `git-embed-button-${buttonSize}`;
                
                const downloadInfo = repoData.download_info || {};
                const downloadUrl = downloadInfo.url || repoData.archive_url || '';
                const downloadFilename = downloadInfo.filename || `${repoData.name}.zip`;
                const downloadTitle = downloadInfo.name ? 
                    __('Download', 'git-embed-feicode') + ' ' + downloadInfo.name + ' (' + downloadInfo.version + ')' : 
                    __('Download ZIP', 'git-embed-feicode');
                
                const displayAvatarUrl = getDisplayAvatarUrl(repoData);

                return el('div', { className: cardClass },
                    showSiteInfo && repoData.site_info && el('div', { 
                        className: `git-embed-site-info platform-${repoData.platform || 'github'}` 
                    },
                        el('img', {
                            src: repoData.site_info.favicon,
                            alt: repoData.site_info.name,
                            className: 'git-embed-site-favicon',
                            onError: (e) => e.target.style.display = 'none'
                        }),
                        el('span', { className: 'git-embed-site-name' },
                            el('a', {
                                href: repoData.site_info.url,
                                target: '_blank',
                                rel: 'noopener'
                            }, repoData.site_info.name)
                        )
                    ),
                    
                    el('div', { className: 'git-embed-header' },
                        el('div', { className: 'git-embed-title-section' },
                            showAvatar && displayAvatarUrl && el('img', {
                                src: displayAvatarUrl,
                                alt: repoData.name,
                                className: avatarClass,
                                title: repoData.repo_avatar_url ? __('Repository Avatar', 'git-embed-feicode') : __('Owner Avatar', 'git-embed-feicode')
                            }),
                            el('div', { className: 'git-embed-title-content' },
                                el('h3', { className: 'git-embed-title' },
                                    el('span', { className: 'dashicons dashicons-embed-generic git-embed-repo-icon' }),
                                    el('a', {
                                        href: repoData.html_url,
                                        target: '_blank',
                                        rel: 'noopener'
                                    }, repoData.full_name)
                                ),
                                showAvatar && repoData.owner && el('div', { className: 'git-embed-owner-info' },
                                    repoData.owner.type && el('span', { className: 'git-embed-owner-type' }, repoData.owner.type),
                                    el('a', {
                                        href: repoData.owner.html_url,
                                        target: '_blank',
                                        rel: 'noopener',
                                        className: 'git-embed-owner-link'
                                    }, `@${repoData.owner.login}`),
                                    repoData.repo_avatar_url && el('span', { 
                                        className: 'git-embed-repo-avatar-badge',
                                        title: __('Repository has custom avatar', 'git-embed-feicode')
                                    },
                                        el('span', { className: '' })
                                    )
                                )
                            )
                        ),
                        el('div', { className: 'git-embed-meta-section' },
                            showLanguage && repoData.language && el('span', { className: 'git-embed-language' },
                                el('span', { className: 'dashicons dashicons-editor-code' }),
                                repoData.language
                            ),
                            el('span', { 
                                className: `git-embed-platform-badge platform-${repoData.platform || 'github'}` 
                            }, repoData.platform ? repoData.platform.toUpperCase() : 'GITHUB')
                        )
                    ),
                    
                    showDescription && repoData.description &&
                        el('p', { className: 'git-embed-description' },
                            el('span', { className: 'dashicons dashicons-editor-quote' }),
                            repoData.description
                        ),
                    showStats && el('div', { className: 'git-embed-stats' },
                        el('span', { className: 'git-embed-stat' },
                            el('span', { className: 'dashicons dashicons-star-filled' }),
                            el('span', { className: 'git-embed-stat-label' }, __('Stars:', 'git-embed-feicode')),
                            el('span', { className: 'git-embed-stat-value' }, repoData.stargazers_count.toLocaleString())
                        ),
                        el('span', { className: 'git-embed-stat' },
                            el('span', { className: 'dashicons dashicons-networking' }),
                            el('span', { className: 'git-embed-stat-label' }, __('Forks:', 'git-embed-feicode')),
                            el('span', { className: 'git-embed-stat-value' }, repoData.forks_count.toLocaleString())
                        ),
                        el('span', { className: 'git-embed-stat' },
                            el('span', { className: 'dashicons dashicons-editor-help' }),
                            el('span', { className: 'git-embed-stat-label' }, __('Issues:', 'git-embed-feicode')),
                            el('span', { className: 'git-embed-stat-value' }, repoData.open_issues_count.toLocaleString())
                        )
                    ),
                    showActions && (showViewButton || showCloneButton || showDownloadButton || showIssuesButton || showForksButton) && 
                        el('div', { className: 'git-embed-actions' },
                            showViewButton && el('a', {
                                href: repoData.html_url,
                                className: `git-embed-button git-embed-button-${buttonStyle} ${buttonClass}`,
                                target: '_blank',
                                rel: 'noopener'
                            }, 
                                el('span', { className: 'dashicons dashicons-external' }),
                                __('View Repository', 'git-embed-feicode')
                            ),
                            showCloneButton && el('span', {
                                className: `git-embed-button git-embed-button-secondary ${buttonClass}`,
                                title: __('Clone URL:', 'git-embed-feicode') + ' ' + repoData.clone_url
                            }, 
                                el('span', { className: 'dashicons dashicons-admin-page' }),
                                __('Clone', 'git-embed-feicode')
                            ),
                            showDownloadButton && downloadUrl && el('button', {
                                className: `git-embed-button git-embed-button-secondary ${buttonClass}`,
                                onClick: () => handleDownload(downloadUrl, downloadFilename),
                                title: downloadTitle
                            }, 
                                el('span', { className: 'dashicons dashicons-download' }),
                                downloadInfo.type === 'release' && downloadInfo.version ? 
                                    __('Download', 'git-embed-feicode') + ' ' + downloadInfo.version : 
                                    __('Download ZIP', 'git-embed-feicode')
                            ),
                            showIssuesButton && el('a', {
                                href: `${repoData.html_url}/issues`,
                                className: `git-embed-button git-embed-button-outline ${buttonClass}`,
                                target: '_blank',
                                rel: 'noopener'
                            }, 
                                el('span', { className: 'dashicons dashicons-editor-help' }),
                                __('Issues', 'git-embed-feicode') + ' (' + repoData.open_issues_count.toLocaleString() + ')'
                            ),
                            showForksButton && el('a', {
                                href: `${repoData.html_url}/forks`,
                                className: `git-embed-button git-embed-button-outline ${buttonClass}`,
                                target: '_blank',
                                rel: 'noopener'
                            }, 
                                el('span', { className: 'dashicons dashicons-networking' }),
                                __('Forks', 'git-embed-feicode') + ' (' + repoData.forks_count.toLocaleString() + ')'
                            )
                        )
                );
            };

            return el('div', blockProps,
                el(BlockControls, null,
                    el(BlockAlignmentToolbar, {
                        value: alignment,
                        onChange: (value) => setAttributes({ alignment: value })
                    })
                ),
                el(InspectorControls, null,
                    el(PanelBody, {
                        title: __('Repository Settings', 'git-embed-feicode'),
                        initialOpen: true
                    },
                        el(SelectControl, {
                            label: __('Platform', 'git-embed-feicode'),
                            value: platform,
                            options: [
                                { label: __('GitHub', 'git-embed-feicode'), value: 'github' },
                                { label: __('Gitea', 'git-embed-feicode'), value: 'gitea' },
                                { label: __('Forgejo', 'git-embed-feicode'), value: 'forgejo' },
                                { label: __('GitLab (Self-hosted)', 'git-embed-feicode'), value: 'gitlab' },
                                { label: __('Custom Git Service', 'git-embed-feicode'), value: 'custom' }
                            ],
                            onChange: (value) => setAttributes({ platform: value }),
                            help: platform !== 'github' ? __('Self-hosted Git service requires custom domain', 'git-embed-feicode') : '',
                            __next40pxDefaultSize: true,
                            __nextHasNoMarginBottom: true
                        }),
                        (platform !== 'github') && el(TextControl, {
                            label: __('Custom Domain', 'git-embed-feicode'),
                            value: customDomain,
                            onChange: (value) => setAttributes({ customDomain: value }),
                            placeholder: __('e.g. git.example.com', 'git-embed-feicode'),
                            help: __('Enter the domain of your', 'git-embed-feicode') + ' ' + platform.charAt(0).toUpperCase() + platform.slice(1) + ' ' + __('instance', 'git-embed-feicode'),
                            __next40pxDefaultSize: true,
                            __nextHasNoMarginBottom: true
                        }),
                        (platform !== 'github') && el(TextControl, {
                            label: __('Custom Site Name (Optional)', 'git-embed-feicode'),
                            value: customSiteName,
                            onChange: (value) => setAttributes({ customSiteName: value }),
                            placeholder: __('e.g. Company Git', 'git-embed-feicode'),
                            help: __('Override the automatically detected site name', 'git-embed-feicode'),
                            __next40pxDefaultSize: true,
                            __nextHasNoMarginBottom: true
                        }),
                        el(TextControl, {
                            label: __('Repository Owner', 'git-embed-feicode'),
                            value: owner,
                            onChange: (value) => setAttributes({ owner: value }),
                            placeholder: __('e.g. facebook', 'git-embed-feicode'),
                            __next40pxDefaultSize: true,
                            __nextHasNoMarginBottom: true
                        }),
                        el(TextControl, {
                            label: __('Repository Name', 'git-embed-feicode'),
                            value: repo,
                            onChange: (value) => setAttributes({ repo: value }),
                            placeholder: __('e.g. react', 'git-embed-feicode'),
                            __next40pxDefaultSize: true,
                            __nextHasNoMarginBottom: true
                        }),
                        el(Button, {
                            isPrimary: true,
                            onClick: fetchRepoData,
                            disabled: loading || !owner || !repo || 
                                (platform !== 'github' && !customDomain)
                        }, loading ? __('Fetching...', 'git-embed-feicode') : __('Fetch Repository', 'git-embed-feicode'))
                    ),
                    el(PanelBody, {
                        title: __('Display Options', 'git-embed-feicode'),
                        initialOpen: false
                    },
                        el(ToggleControl, {
                            label: __('Show Site Information', 'git-embed-feicode'),
                            checked: showSiteInfo,
                            onChange: (value) => setAttributes({ showSiteInfo: value })
                        }),
                        el(ToggleControl, {
                            label: __('Show Avatar', 'git-embed-feicode'),
                            checked: showAvatar,
                            onChange: (value) => setAttributes({ showAvatar: value }),
                            help: __('Shows repository avatar if available, otherwise owner avatar', 'git-embed-feicode')
                        }),
                        showAvatar && el(SelectControl, {
                            label: __('Avatar Size', 'git-embed-feicode'),
                            value: avatarSize,
                            options: [
                                { label: __('Small', 'git-embed-feicode'), value: 'small' },
                                { label: __('Medium', 'git-embed-feicode'), value: 'medium' },
                                { label: __('Large', 'git-embed-feicode'), value: 'large' }
                            ],
                            onChange: (value) => setAttributes({ avatarSize: value }),
                            __next40pxDefaultSize: true,
                            __nextHasNoMarginBottom: true
                        }),
                        el(ToggleControl, {
                            label: __('Show Description', 'git-embed-feicode'),
                            checked: showDescription,
                            onChange: (value) => setAttributes({ showDescription: value })
                        }),
                        el(ToggleControl, {
                            label: __('Show Programming Language', 'git-embed-feicode'),
                            checked: showLanguage,
                            onChange: (value) => setAttributes({ showLanguage: value })
                        }),
                        el(ToggleControl, {
                            label: __('Show Statistics', 'git-embed-feicode'),
                            checked: showStats,
                            onChange: (value) => setAttributes({ showStats: value })
                        }),
                        el(ToggleControl, {
                            label: __('Show Action Buttons', 'git-embed-feicode'),
                            checked: showActions,
                            onChange: (value) => setAttributes({ showActions: value })
                        })
                    ),
                    el(PanelBody, {
                        title: __('Button Options', 'git-embed-feicode'),
                        initialOpen: false
                    },
                        el(ToggleControl, {
                            label: __('Show View Repository Button', 'git-embed-feicode'),
                            checked: showViewButton,
                            onChange: (value) => setAttributes({ showViewButton: value }),
                            disabled: !showActions
                        }),
                        el(ToggleControl, {
                            label: __('Show Clone Button', 'git-embed-feicode'),
                            checked: showCloneButton,
                            onChange: (value) => setAttributes({ showCloneButton: value }),
                            disabled: !showActions
                        }),
                        el(ToggleControl, {
                            label: __('Show Download ZIP Button', 'git-embed-feicode'),
                            checked: showDownloadButton,
                            onChange: (value) => setAttributes({ showDownloadButton: value }),
                            disabled: !showActions
                        }),
                        el(ToggleControl, {
                            label: __('Show Issues Button', 'git-embed-feicode'),
                            checked: showIssuesButton,
                            onChange: (value) => setAttributes({ showIssuesButton: value }),
                            disabled: !showActions
                        }),
                        el(ToggleControl, {
                            label: __('Show Forks Button', 'git-embed-feicode'),
                            checked: showForksButton,
                            onChange: (value) => setAttributes({ showForksButton: value }),
                            disabled: !showActions
                        }),
                        el(SelectControl, {
                            label: __('Button Style', 'git-embed-feicode'),
                            value: buttonStyle,
                            options: [
                                { label: __('Default', 'git-embed-feicode'), value: 'default' },
                                { label: __('Primary (Green)', 'git-embed-feicode'), value: 'primary' },
                                { label: __('Secondary (Gray)', 'git-embed-feicode'), value: 'secondary' },
                                { label: __('Outline', 'git-embed-feicode'), value: 'outline' },
                                { label: __('Ghost', 'git-embed-feicode'), value: 'ghost' }
                            ],
                            onChange: (value) => setAttributes({ buttonStyle: value }),
                            disabled: !showActions,
                            __next40pxDefaultSize: true,
                            __nextHasNoMarginBottom: true
                        }),
                        el(SelectControl, {
                            label: __('Button Size', 'git-embed-feicode'),
                            value: buttonSize,
                            options: [
                                { label: __('Small', 'git-embed-feicode'), value: 'small' },
                                { label: __('Medium', 'git-embed-feicode'), value: 'medium' },
                                { label: __('Large', 'git-embed-feicode'), value: 'large' }
                            ],
                            onChange: (value) => setAttributes({ buttonSize: value }),
                            disabled: !showActions,
                            __next40pxDefaultSize: true,
                            __nextHasNoMarginBottom: true
                        })
                    ),
                    el(PanelBody, {
                        title: __('Style Options', 'git-embed-feicode'),
                        initialOpen: false
                    },
                        el(SelectControl, {
                            label: __('Card Style', 'git-embed-feicode'),
                            value: cardStyle,
                            options: [
                                { label: __('Default', 'git-embed-feicode'), value: 'default' },
                                { label: __('Minimal', 'git-embed-feicode'), value: 'minimal' },
                                { label: __('Bordered', 'git-embed-feicode'), value: 'bordered' },
                                { label: __('Shadow', 'git-embed-feicode'), value: 'shadow' },
                                { label: __('Gradient', 'git-embed-feicode'), value: 'gradient' },
                                { label: __('Glassmorphism', 'git-embed-feicode'), value: 'glass' }
                            ],
                            onChange: (value) => setAttributes({ cardStyle: value }),
                            __next40pxDefaultSize: true,
                            __nextHasNoMarginBottom: true
                        })
                    )
                ),
                el('div', { className: 'wp-block-git-embed-feicode-repository' },
                    renderPreview()
                )
            );
        },

        save: function() {
            return null;
        }
    });
})();