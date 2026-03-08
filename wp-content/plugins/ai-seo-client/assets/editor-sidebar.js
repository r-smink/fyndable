( function( wp ) {
	const { registerPlugin } = wp.plugins;
	const { PluginSidebar, PluginSidebarMoreMenuItem } = wp.editPost;
	const { PanelBody, TextControl, Button, Notice, SelectControl, TextareaControl, CheckboxControl } = wp.components;
	const { useState } = wp.element;
	const { apiFetch } = wp;
	const { dispatch, select } = wp.data;

	const presets = (window.AISEOAssistantSettings?.presets || []).filter(Boolean);
	const defaultTone = window.AISEOAssistantSettings?.tone || '';
	const prompts = window.AISEOAssistantSettings?.prompts || [];
	const notes = window.AISEOAssistantSettings?.notes || [];
	if (!presets.length) {
		presets.push(
			'Blog: Schrijf een SEO-outline met H1/H2/H3, bullets en suggesties voor interne links.',
			'FAQ: Maak een FAQ-sectie met 8 vragen en korte antwoorden, gestructureerd voor schema.',
			'Productpagina: Genereer structuur met USP\'s, specs, voordelen, FAQ en CTA.',
			'Landing: Hero, proof, benefits, features, FAQ, CTA.',
			'Category/Comparison: Overzicht, filters, top picks, FAQ, CTA.',
			'Topical map: Geef H2/H3 clusteropzet rond het onderwerp met interne link-aanwijzingen.'
		);
	}

	const ACTIONS = [
		{ key: 'outline', label: 'Outline' },
		{ key: 'rewrite', label: 'Rewrite selectie' },
		{ key: 'faq', label: 'FAQ toevoegen' },
		{ key: 'links', label: 'Interne links' },
		{ key: 'image', label: 'Afbeelding genereren' },
		{ key: 'improve', label: 'Improve alinea' },
		{ key: 'expand', label: 'Paragraaf uitbreiden' },
		{ key: 'cta', label: 'CTA toevoegen' },
	];

	const Sidebar = () => {
		const [topic, setTopic] = useState('');
		const [tone, setTone] = useState(defaultTone);
		const [preset, setPreset] = useState(presets[0] || 'Schrijf een SEO-outline met H1/H2/H3 en bullets.');
		const [output, setOutput] = useState('');
		const [imageUrl, setImageUrl] = useState('');
		const [busy, setBusy] = useState(false);
		const [error, setError] = useState('');
		const [action, setAction] = useState('outline');
		const [selectionText, setSelectionText] = useState('');
		const [promptId, setPromptId] = useState(prompts[0]?.id || '');
		const [noteIds, setNoteIds] = useState([]);
		const [afterText, setAfterText] = useState('');

		const toggleNote = (id) => {
			setNoteIds((prev) => prev.includes(id) ? prev.filter(x => x !== id) : [...prev, id]);
		};

		const applyDiff = () => {
			if (!afterText || !selectionText) return;
			const current = select('core/editor').getEditedPostContent();
			const replaced = current.replace(selectionText, afterText);
			if (replaced !== current) {
				dispatch('core/editor').editPost({ content: replaced });
			}
		};

		const generate = async () => {
			const content = select('core/editor').getEditedPostContent();
			if (action === 'rewrite' && !selectionText) {
				setError('Zet een geselecteerd stuk tekst of vul handmatig in.');
				return;
			}
			if (['improve','expand','cta'].includes(action) && !selectionText) {
				setError('Selecteer of plak een alinea om te bewerken.');
				return;
			}
			if (!topic && !['rewrite','image','improve','expand','cta'].includes(action)) {
				setError('Voer een topic in.');
				return;
			}
			if (action === 'image' && !topic) {
				setError('Voer een beeldprompt in het topicveld.');
				return;
			}
			setBusy(true);
			setError('');
			setOutput('');
			setImageUrl('');
			setAfterText('');

			try {
				if (action === 'image') {
					const res = await apiFetch({ path: '/aiseoclient/v1/editor-image', method: 'POST', data: { prompt: topic } });
					setImageUrl(res?.url || '');
					return;
				}
				const res = await apiFetch({
					path: '/aiseoclient/v1/editor-action',
					method: 'POST',
					data: { action, topic, preset, tone, content, selection: selectionText, prompt_id: promptId, notes: noteIds }
				});
				const text = res?.text || '';
				setOutput(text);
				if (selectionText && ['rewrite','improve','expand','cta'].includes(action)) {
					setAfterText(text);
				} else if (text) {
					const current = select('core/editor').getEditedPostContent();
					dispatch('core/editor').editPost({ content: current + '\n\n' + text });
				}
			} catch (e) {
				setError('Kon geen resultaat ophalen.');
			} finally {
				setBusy(false);
			}
		};

		return (
			<>
				<PluginSidebarMoreMenuItem target="ai-seo-assistant-sidebar">AI SEO</PluginSidebarMoreMenuItem>
				<PluginSidebar name="ai-seo-assistant-sidebar" title="AI SEO">
					<PanelBody title="Actie" initialOpen={true}>
						<SelectControl value={action} onChange={setAction} options={ACTIONS.map(a => ({ label: a.label, value: a.key }))} />
					</PanelBody>
					<PanelBody title="Prompt" initialOpen={true}>
						<SelectControl label="Preset" value={preset} onChange={setPreset} options={(presets.length ? presets : [preset]).map((p) => ({ label: p.slice(0, 80), value: p }))} disabled={action==='image'} />
						<TextControl label="Toon" value={tone} onChange={setTone} placeholder="Deskundig, helder" disabled={action==='image'} />
						{prompts.length > 0 && action!=='image' && (
							<SelectControl label="Prompt template" value={promptId} onChange={setPromptId} options={[{ label: 'Geen', value: '' }, ...prompts.map(p => ({ label: p.title, value: String(p.id) }))]} />
						)}
					</PanelBody>
					{action!=='image' && (
					<PanelBody title="Kennis" initialOpen={false}>
						{notes.length === 0 && <p style={{ color: '#666' }}>Geen notes beschikbaar.</p>}
						{notes.map(n => (
							<CheckboxControl key={n.id} label={n.title} checked={noteIds.includes(n.id)} onChange={() => toggleNote(n.id)} />
						))}
					</PanelBody>
					)}
					<PanelBody title="Invoer" initialOpen={true}>
						<TextControl
							label={action === 'image' ? 'Beeldprompt' : 'Onderwerp / keyword'}
							value={topic}
							onChange={setTopic}
							disabled={action === 'rewrite'}
						/>
						{['rewrite','improve','expand','cta'].includes(action) && (
							<TextareaControl label="Te herschrijven / verbeteren" value={selectionText} onChange={setSelectionText} />
						)}
					</PanelBody>
					<Button isPrimary isBusy={busy} onClick={generate} style={{ marginTop: '8px' }}>
						Genereer
					</Button>
					{error && <Notice status="error" isDismissible={false}>{error}</Notice>}
					{output && !afterText && (
						<PanelBody title="Resultaat" initialOpen={true}>
							<pre style={{ whiteSpace: 'pre-wrap' }}>{output}</pre>
						</PanelBody>
					)}
					{afterText && selectionText && (
						<PanelBody title="Diff" initialOpen={true}>
							<p><strong>Oud</strong></p>
							<pre style={{ whiteSpace: 'pre-wrap', background:'#fee' }}>{selectionText}</pre>
							<p><strong>Nieuw</strong></p>
							<pre style={{ whiteSpace: 'pre-wrap', background:'#eef' }}>{afterText}</pre>
							<Button variant="primary" onClick={applyDiff}>Vervang selectie</Button>
						</PanelBody>
					)}
					{imageUrl && (
						<PanelBody title="Afbeelding" initialOpen={true}>
							<img src={imageUrl} style={{ maxWidth: '100%' }} />
						</PanelBody>
					)}
				</PluginSidebar>
			</>
		);
	};

	registerPlugin('ai-seo-assistant-sidebar', { render: Sidebar });
})( window.wp );
