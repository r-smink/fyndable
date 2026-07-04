( function( wp ) {
	const { registerPlugin } = wp.plugins;
	const { PluginSidebar, PluginSidebarMoreMenuItem } = wp.editPost;
	const { PanelBody, TextControl, TextareaControl, Button, Spinner } = wp.components;
	const { useState, useEffect, useCallback, useRef } = wp.element;
	const { useSelect } = wp.data;
	const { apiFetch } = wp;

	const debounce = (fn, ms) => {
		let timer;
		return (...args) => {
			clearTimeout(timer);
			timer = setTimeout(() => fn(...args), ms);
		};
	};

	const ScoreCircle = ({ score }) => {
		const color = score >= 80 ? '#00a32a' : (score >= 50 ? '#dba617' : '#d63638');
		const label = score >= 80 ? 'Great' : (score >= 50 ? 'OK' : 'Needs Work');
		const dashArray = `${score}, 100`;

		return wp.element.createElement('div', { style: { textAlign: 'center', padding: '10px 0' } },
			wp.element.createElement('svg', { viewBox: '0 0 36 36', width: 90, height: 90, style: { transform: 'rotate(-90deg)' } },
				wp.element.createElement('circle', { cx: 18, cy: 18, r: 15.9, fill: 'none', stroke: '#e0e0e0', strokeWidth: 3 }),
				wp.element.createElement('circle', { cx: 18, cy: 18, r: 15.9, fill: 'none', stroke: color, strokeWidth: 3, strokeDasharray: dashArray, strokeLinecap: 'round', style: { transition: 'stroke-dasharray 0.5s ease' } })
			),
			wp.element.createElement('div', { style: { marginTop: -60, fontSize: 22, fontWeight: 'bold', color: color, position: 'relative' } }, score),
			wp.element.createElement('div', { style: { fontSize: 12, color: '#666', marginTop: 35 } }, label)
		);
	};

	const AnalysisItem = ({ item }) => {
		const colors = { good: '#00a32a', warning: '#dba617', error: '#d63638' };
		const icons = { good: '✓', warning: '▲', error: '✕' };
		const bgColors = { good: '#edfaef', warning: '#fcf9e8', error: '#fcf0f1' };

		return wp.element.createElement('div', {
			style: {
				padding: '8px 10px',
				marginBottom: 4,
				borderLeft: `3px solid ${colors[item.status] || '#ddd'}`,
				background: bgColors[item.status] || '#f9f9f9',
				borderRadius: '0 4px 4px 0',
				fontSize: 13,
			}
		},
			wp.element.createElement('span', { style: { color: colors[item.status], marginRight: 6, fontWeight: 'bold' } }, icons[item.status]),
			wp.element.createElement('strong', null, item.label + ': '),
			item.message
		);
	};

	const SeoSidebar = () => {
		const [keyphrase, setKeyphrase] = useState('');
		const [seoTitle, setSeoTitle] = useState('');
		const [seoDesc, setSeoDesc] = useState('');
		const [score, setScore] = useState(null);
		const [analysis, setAnalysis] = useState([]);
		const [loading, setLoading] = useState(false);
		const [generating, setGenerating] = useState(false);
		const [saved, setSaved] = useState(false);
		const analysisTimer = useRef(null);

		const { postTitle, postContent, postId } = useSelect((select) => {
			const editor = select('core/editor');
			return {
				postTitle: editor.getEditedPostAttribute('title') || '',
				postContent: editor.getEditedPostAttribute('content') || '',
				postId: editor.getCurrentPostId(),
			};
		});

		// Load saved meta on mount
		useEffect(() => {
			if (!postId) return;
			apiFetch({ path: `/wp/v2/posts/${postId}` }).then((post) => {
				if (post.meta) {
					if (post.meta._aiseo_focus_keyphrase) setKeyphrase(post.meta._aiseo_focus_keyphrase);
					if (post.meta._aiseo_title) setSeoTitle(post.meta._aiseo_title);
					if (post.meta._aiseo_description) setSeoDesc(post.meta._aiseo_description);
				}
			}).catch(() => {});
		}, [postId]);

		const runAnalysis = useCallback(() => {
			if (!postContent && !postTitle) return;
			setLoading(true);
			apiFetch({
				path: '/sseo-ai/v1/analyze',
				method: 'POST',
				data: {
					content: postContent,
					title: postTitle,
					keyphrase: keyphrase,
				}
			}).then((res) => {
				setScore(res.score);
				setAnalysis(res.analysis || []);
			}).catch(() => {}).finally(() => setLoading(false));
		}, [postContent, postTitle, keyphrase]);

		// Auto-analyze when content changes (debounced)
		useEffect(() => {
			if (analysisTimer.current) clearTimeout(analysisTimer.current);
			analysisTimer.current = setTimeout(runAnalysis, 2000);
			return () => clearTimeout(analysisTimer.current);
		}, [postContent, postTitle, keyphrase]);

		// AI generate meta
		const generateMeta = () => {
			if (!postId) return;
			setGenerating(true);
			apiFetch({
				path: '/sseo-ai/v1/bulk/generate-meta',
				method: 'POST',
				data: {
					post_id: postId,
					fields: ['seo_title', 'seo_description', 'focus_keyphrase']
				}
			}).then((res) => {
				if (res.generated) {
					if (res.generated.seo_title) setSeoTitle(res.generated.seo_title);
					if (res.generated.seo_description) setSeoDesc(res.generated.seo_description);
					if (res.generated.focus_keyphrase) setKeyphrase(res.generated.focus_keyphrase);
				}
			}).catch((err) => {
				console.error('AI generate failed:', err);
			}).finally(() => setGenerating(false));
		};

		// Save meta via REST (for Gutenberg, meta is saved with post)
		const saveMeta = () => {
			if (!postId) return;
			apiFetch({
				path: `/wp/v2/posts/${postId}`,
				method: 'POST',
				data: {
					meta: {
						_aiseo_focus_keyphrase: keyphrase,
						_aiseo_title: seoTitle,
						_aiseo_description: seoDesc,
					}
				}
			}).then(() => {
				setSaved(true);
				setTimeout(() => setSaved(false), 2000);
				runAnalysis();
			}).catch((err) => console.error('Save failed:', err));
		};

		// SERP preview
		const siteUrl = window.location.origin;
		const previewTitle = seoTitle || postTitle || 'Untitled';
		const previewDesc = seoDesc || 'Add a meta description for better search visibility...';

		// Count helpers
		const titleLen = (seoTitle || '').length;
		const descLen = (seoDesc || '').length;
		const titleColor = titleLen >= 30 && titleLen <= 60 ? '#00a32a' : (titleLen > 0 ? '#dba617' : '#999');
		const descColor = descLen >= 120 && descLen <= 160 ? '#00a32a' : (descLen > 0 ? '#dba617' : '#999');

		return wp.element.createElement(wp.element.Fragment, null,
			wp.element.createElement(PluginSidebarMoreMenuItem, { target: 'aiseo-score-sidebar' }, 'AI SEO Score'),
			wp.element.createElement(PluginSidebar, {
				name: 'aiseo-score-sidebar',
				title: 'AI SEO Score',
				icon: 'chart-bar',
			},
				// Score
				wp.element.createElement(PanelBody, { title: 'SEO Score', initialOpen: true },
					score !== null
						? wp.element.createElement(ScoreCircle, { score: score })
						: wp.element.createElement('div', { style: { textAlign: 'center', padding: 20, color: '#999' } },
							loading ? wp.element.createElement(Spinner, null) : 'Start typing to see your score'
						),
					wp.element.createElement(Button, {
						variant: 'secondary',
						onClick: runAnalysis,
						isBusy: loading,
						style: { width: '100%', justifyContent: 'center', marginTop: 8 }
					}, 'Re-analyze')
				),

				// SERP Preview
				wp.element.createElement(PanelBody, { title: 'Google Preview', initialOpen: true },
					wp.element.createElement('div', { style: { background: '#fff', border: '1px solid #ddd', borderRadius: 8, padding: 12, fontFamily: 'arial, sans-serif' } },
						wp.element.createElement('div', { style: { fontSize: 18, color: '#1a0dab', lineHeight: 1.3, marginBottom: 3 } }, previewTitle.substring(0, 60)),
						wp.element.createElement('div', { style: { fontSize: 14, color: '#006621', marginBottom: 3 } }, siteUrl),
						wp.element.createElement('div', { style: { fontSize: 14, color: '#4d5156', lineHeight: 1.4 } }, previewDesc.substring(0, 160))
					)
				),

				// Meta Fields
				wp.element.createElement(PanelBody, { title: 'SEO Meta', initialOpen: true },
					wp.element.createElement(TextControl, {
						label: 'Focus Keyphrase',
						value: keyphrase,
						onChange: setKeyphrase,
						placeholder: 'Enter your main keyword...',
					}),
					wp.element.createElement(TextControl, {
						label: 'SEO Title',
						value: seoTitle,
						onChange: setSeoTitle,
						placeholder: postTitle,
						maxLength: 60,
						help: wp.element.createElement('span', { style: { color: titleColor } }, titleLen + '/60 characters')
					}),
					wp.element.createElement(TextareaControl, {
						label: 'Meta Description',
						value: seoDesc,
						onChange: setSeoDesc,
						placeholder: 'Write a compelling meta description...',
						maxLength: 160,
						help: wp.element.createElement('span', { style: { color: descColor } }, descLen + '/160 characters')
					}),
					wp.element.createElement('div', { style: { display: 'flex', gap: 8 } },
						wp.element.createElement(Button, {
							variant: 'primary',
							onClick: saveMeta,
							style: { flex: 1, justifyContent: 'center' }
						}, saved ? '✓ Saved!' : 'Save Meta'),
						wp.element.createElement(Button, {
							variant: 'secondary',
							onClick: generateMeta,
							isBusy: generating,
							style: { flex: 1, justifyContent: 'center' }
						}, 'AI Generate')
					)
				),

				// Analysis
				wp.element.createElement(PanelBody, { title: 'Analysis (' + analysis.length + ' checks)', initialOpen: false },
					analysis.length > 0
						? analysis.map((item, i) => wp.element.createElement(AnalysisItem, { key: i, item: item }))
						: wp.element.createElement('p', { style: { color: '#999' } }, 'Run analysis to see results')
				)
			)
		);
	};

	registerPlugin('aiseo-score-sidebar', { render: SeoSidebar });
})( window.wp );
