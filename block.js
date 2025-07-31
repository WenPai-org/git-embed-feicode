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
        icon: 'admin-links',
        category: 'embed',
        supports: {
            align: ['left', 'center', 'right', 'wide', 'full']
        },
        attributes: {
            platform: {
                type: 'string',
                default: 'github'
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
                default: 'primary'
            },
            alignment: {
                type: 'string',
                default: 'none'
            }
        },

        edit: function(props) {
            const { attributes, setAttributes } = props;
            const { 
                platform, owner, repo, showDescription, showStats, showLanguage,
                showActions, showViewButton, showCloneButton, showDownloadButton,
                showAvatar, showSiteInfo, avatarSize, cardStyle, buttonStyle, alignment 
            } = attributes;
            
            const [repoData, setRepoData] = useState(null);
            const [loading, setLoading] = useState(false);
            const [error, setError] = useState('');

            const blockProps = useBlockProps({
                className: alignment !== 'none' ? `align${alignment}` : ''
            });

            const fetchRepoData = () => {
                if (!owner || !repo) {
                    setError('Please enter repository owner and name');
                    return;
                }

                setLoading(true);
                setError('');

                const formData = new FormData();
                formData.append('action', 'git_embed_fetch');
                formData.append('platform', platform);
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
                        setError(data.data || 'Failed to fetch repository');
                        setRepoData(null);
                    }
                })
                .catch(err => {
                    setLoading(false);
                    setError('Network error occurred');
                    setRepoData(null);
                });
            };

            useEffect(() => {
                if (owner && repo) {
                    fetchRepoData();
                }
            }, [owner, repo, platform]);

            const renderPreview = () => {
                if (loading) {
                    return el('div', { className: 'git-embed-loading' },
                        el(Spinner),
                        el('p', null, 'Fetching repository data...')
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
                            el('span', { className: 'dashicons dashicons-admin-links git-embed-placeholder-icon' }),
                            el('h3', null, 'Git Repository Embed'),
                            el('p', null, 'Configure your repository details in the sidebar')
                        )
                    );
                }

                const cardClass = `git-embed-card${cardStyle !== 'default' ? ` git-embed-card-${cardStyle}` : ''}`;
                const avatarClass = `git-embed-avatar git-embed-avatar-${avatarSize}`;
                const downloadUrl = repoData.archive_url ? 
                    repoData.archive_url.replace('{archive_format}', 'zipball').replace('{/ref}', '/main') : '';

                return el('div', { className: cardClass },
                    showSiteInfo && repoData.site_info && el('div', { className: 'git-embed-site-info' },
                        el('img', {
                            src: repoData.site_info.favicon,
                            alt: repoData.site_info.name,
                            className: 'git-embed-site-favicon'
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
                            showAvatar && repoData.owner && el('img', {
                                src: repoData.owner.avatar_url,
                                alt: repoData.owner.login,
                                className: avatarClass
                            }),
                            el('div', { className: 'git-embed-title-content' },
                                el('h3', { className: 'git-embed-title' },
                                    el('span', { className: 'dashicons dashicons-admin-links git-embed-repo-icon' }),
                                    el('a', {
                                        href: repoData.html_url,
                                        target: '_blank',
                                        rel: 'noopener'
                                    }, repoData.full_name)
                                ),
                                showAvatar && repoData.owner && el('div', { className: 'git-embed-owner-info' },
                                    el('span', { className: 'git-embed-owner-type' }, repoData.owner.type),
                                    el('a', {
                                        href: repoData.owner.html_url,
                                        target: '_blank',
                                        rel: 'noopener',
                                        className: 'git-embed-owner-link'
                                    }, `@${repoData.owner.login}`)
                                )
                            )
                        ),
                        showLanguage && repoData.language && el('span', { className: 'git-embed-language' },
                            el('span', { className: 'dashicons dashicons-editor-code' }),
                            repoData.language
                        )
                    ),
                    
                    showDescription && repoData.description &&
                        el('p', { className: 'git-embed-description' },
                            el('span', { className: 'dashicons dashicons-text-page' }),
                            repoData.description
                        ),
                    showStats && el('div', { className: 'git-embed-stats' },
                        el('span', { className: 'git-embed-stat' },
                            el('span', { className: 'dashicons dashicons-star-filled' }),
                            el('span', { className: 'git-embed-stat-label' }, 'Stars:'),
                            el('span', { className: 'git-embed-stat-value' }, repoData.stargazers_count.toLocaleString())
                        ),
                        el('span', { className: 'git-embed-stat' },
                            el('span', { className: 'dashicons dashicons-networking' }),
                            el('span', { className: 'git-embed-stat-label' }, 'Forks:'),
                            el('span', { className: 'git-embed-stat-value' }, repoData.forks_count.toLocaleString())
                        ),
                        el('span', { className: 'git-embed-stat' },
                            el('span', { className: 'dashicons dashicons-editor-help' }),
                            el('span', { className: 'git-embed-stat-label' }, 'Issues:'),
                            el('span', { className: 'git-embed-stat-value' }, repoData.open_issues_count.toLocaleString())
                        )
                    ),
                    showActions && (showViewButton || showCloneButton || showDownloadButton) && 
                        el('div', { className: 'git-embed-actions' },
                            showViewButton && el('a', {
                                href: repoData.html_url,
                                className: `git-embed-button git-embed-button-${buttonStyle}`,
                                target: '_blank',
                                rel: 'noopener'
                            }, 
                                el('span', { className: 'dashicons dashicons-external' }),
                                'View Repository'
                            ),
                            showCloneButton && el('span', {
                                className: 'git-embed-button git-embed-button-secondary',
                                title: `Clone URL: ${repoData.clone_url}`
                            }, 
                                el('span', { className: 'dashicons dashicons-admin-page' }),
                                'Clone'
                            ),
                            showDownloadButton && el('a', {
                                href: downloadUrl,
                                className: 'git-embed-button git-embed-button-secondary',
                                download: `${repoData.name}.zip`
                            }, 
                                el('span', { className: 'dashicons dashicons-download' }),
                                'Download ZIP'
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
                                { label: 'GitHub', value: 'github' }
                            ],
                            onChange: (value) => setAttributes({ platform: value })
                        }),
                        el(TextControl, {
                            label: __('Repository Owner', 'git-embed-feicode'),
                            value: owner,
                            onChange: (value) => setAttributes({ owner: value }),
                            placeholder: 'e.g. facebook'
                        }),
                        el(TextControl, {
                            label: __('Repository Name', 'git-embed-feicode'),
                            value: repo,
                            onChange: (value) => setAttributes({ repo: value }),
                            placeholder: 'e.g. react'
                        }),
                        el(Button, {
                            isPrimary: true,
                            onClick: fetchRepoData,
                            disabled: loading || !owner || !repo
                        }, loading ? 'Fetching...' : 'Fetch Repository')
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
                            label: __('Show Owner Avatar', 'git-embed-feicode'),
                            checked: showAvatar,
                            onChange: (value) => setAttributes({ showAvatar: value })
                        }),
                        showAvatar && el(SelectControl, {
                            label: __('Avatar Size', 'git-embed-feicode'),
                            value: avatarSize,
                            options: [
                                { label: 'Small', value: 'small' },
                                { label: 'Medium', value: 'medium' },
                                { label: 'Large', value: 'large' }
                            ],
                            onChange: (value) => setAttributes({ avatarSize: value })
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
                        el(SelectControl, {
                            label: __('Button Style', 'git-embed-feicode'),
                            value: buttonStyle,
                            options: [
                                { label: 'Primary (Green)', value: 'primary' },
                                { label: 'Secondary (Gray)', value: 'secondary' },
                                { label: 'Outline', value: 'outline' }
                            ],
                            onChange: (value) => setAttributes({ buttonStyle: value }),
                            disabled: !showActions
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
                                { label: 'Default', value: 'default' },
                                { label: 'Minimal', value: 'minimal' },
                                { label: 'Bordered', value: 'bordered' },
                                { label: 'Shadow', value: 'shadow' }
                            ],
                            onChange: (value) => setAttributes({ cardStyle: value })
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