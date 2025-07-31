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
            showDownload: {
                type: 'boolean',
                default: true
            },
            alignment: {
                type: 'string',
                default: 'none'
            }
        },

        edit: function(props) {
            const { attributes, setAttributes } = props;
            const { platform, owner, repo, showDescription, showStats, showDownload, alignment } = attributes;
            
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
                            el('span', { className: 'git-embed-placeholder-icon' }, '📦'),
                            el('h3', null, 'Git Repository Embed'),
                            el('p', null, 'Configure your repository details in the sidebar')
                        )
                    );
                }

                return el('div', { className: 'git-embed-card' },
                    el('div', { className: 'git-embed-header' },
                        el('h3', { className: 'git-embed-title' },
                            el('a', {
                                href: repoData.html_url,
                                target: '_blank',
                                rel: 'noopener'
                            }, repoData.full_name)
                        ),
                        repoData.language && el('span', { className: 'git-embed-language' }, repoData.language)
                    ),
                    showDescription && repoData.description &&
                        el('p', { className: 'git-embed-description' }, repoData.description),
                    showStats && el('div', { className: 'git-embed-stats' },
                        el('span', { className: 'git-embed-stat' },
                            el('span', { className: 'git-embed-icon' }, '⭐'),
                            repoData.stargazers_count.toLocaleString()
                        ),
                        el('span', { className: 'git-embed-stat' },
                            el('span', { className: 'git-embed-icon' }, '🍴'),
                            repoData.forks_count.toLocaleString()
                        ),
                        el('span', { className: 'git-embed-stat' },
                            el('span', { className: 'git-embed-icon' }, '📝'),
                            repoData.open_issues_count.toLocaleString()
                        )
                    ),
                    showDownload && el('div', { className: 'git-embed-actions' },
                        el('a', {
                            href: repoData.html_url,
                            className: 'git-embed-button git-embed-button-primary',
                            target: '_blank',
                            rel: 'noopener'
                        }, 'View Repository'),
                        el('a', {
                            href: repoData.clone_url,
                            className: 'git-embed-button git-embed-button-secondary'
                        }, 'Clone')
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
                            label: __('Show Description', 'git-embed-feicode'),
                            checked: showDescription,
                            onChange: (value) => setAttributes({ showDescription: value })
                        }),
                        el(ToggleControl, {
                            label: __('Show Statistics', 'git-embed-feicode'),
                            checked: showStats,
                            onChange: (value) => setAttributes({ showStats: value })
                        }),
                        el(ToggleControl, {
                            label: __('Show Download Links', 'git-embed-feicode'),
                            checked: showDownload,
                            onChange: (value) => setAttributes({ showDownload: value })
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