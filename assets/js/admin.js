const channelsContainer = document.getElementById('buoyvs-channels')
const singleSyncRules  = document.getElementById('buoyvs-rules')
const delegationRoot   = channelsContainer || singleSyncRules

if (delegationRoot) {

/**
 * Update the rule label span from the selected action + schedule.
 */
const scheduleSuffixes = {
	once:    'immediately after enabling and saving',
	hourly:  'every hour',
	daily:   'every day',
	weekly:  'every week',
	monthly: 'every month',
}

function updateRuleLabel(rule) {
	const label = rule.querySelector('.buoyvs-rule-heading')
	if (!label) return

	const actionSelect   = rule.querySelector('.buoyvs-action')
	const scheduleSelect = rule.querySelector('.buoyvs-sync-schedule')
	const customInput    = rule.querySelector('.buoyvs-custom-sync-schedule')

	const selectedAction = actionSelect?.selectedOptions[0]
	const hasAction = selectedAction && selectedAction.value

	if (!hasAction) {
		label.textContent = 'Please select an action.'
		rule.classList.add('buoyvs-rule--no-action')
		return
	}

	rule.classList.remove('buoyvs-rule--no-action')

	const actionText = selectedAction.textContent.trim()
	const scheduleValue = scheduleSelect?.value || 'once'
	const suffix = scheduleValue === 'custom'
		? `every ${customInput?.value || 1} hours`
		: (scheduleSuffixes[scheduleValue] || scheduleValue)

	label.textContent = actionText + ' ' + suffix + '.'
}

// Init labels on load
delegationRoot.querySelectorAll('.buoyvs-rule').forEach(updateRuleLabel)

/**
 * Get a localStorage key for a sync rule's accordion state.
 */
function getRuleStorageKey(rule) {
	const channel = rule.closest('.buoyvs-channel')
	const chIdx   = channel ? channel.dataset.channelIndex : 'single'
	return `buoyvs_accordion_ch${chIdx}_rule${rule.dataset.ruleIndex}`
}

/**
 * Toggle accordion expand/collapse on a sync rule.
 */
function toggleRuleAccordion(rule, header) {
	const isExpanded = header.getAttribute('aria-expanded') === 'true'
	header.setAttribute('aria-expanded', isExpanded ? 'false' : 'true')
	rule.classList.toggle('buoyvs-collapsed', isExpanded)
	try { localStorage.setItem(getRuleStorageKey(rule), isExpanded ? 'collapsed' : 'expanded') } catch (e) {}
}

// Restore accordion state from localStorage on page load, then reveal containers
delegationRoot.querySelectorAll('.buoyvs-rule').forEach(function(rule) {
	try {
		if (localStorage.getItem(getRuleStorageKey(rule)) === 'collapsed') {
			rule.classList.add('buoyvs-collapsed')
			const header = rule.querySelector('.buoyvs-rule-header')
			if (header) header.setAttribute('aria-expanded', 'false')
		}
	} catch (e) {}
})
delegationRoot.querySelectorAll('.buoyvs-rules--init').forEach(function(el) {
	el.classList.remove('buoyvs-rules--init')
})

// Click handler
delegationRoot.addEventListener('click', function(e) {
	const header = e.target.closest('.buoyvs-rule-header')
	if (header && !e.target.closest('button, label')) {
		const rule = header.closest('.buoyvs-rule')
		if (rule) toggleRuleAccordion(rule, header)
	}
})

// Keyboard handler
delegationRoot.addEventListener('keydown', function(e) {
	if (e.key !== 'Enter' && e.key !== ' ') return
	if (!e.target.classList.contains('buoyvs-rule-header')) return
	e.preventDefault()
	const rule = e.target.closest('.buoyvs-rule')
	if (rule) toggleRuleAccordion(rule, e.target)
})

/**
 * Reindex all sync rules within a container to ensure sequential numbering (0, 1, 2...)
 */
function reindexRules(container) {
	const rules = container.querySelectorAll('.buoyvs-rule')
	rules.forEach((rule, newIndex) => {
		const oldIndex = rule.getAttribute('data-rule-index')

		// Update data attribute
		rule.setAttribute('data-rule-index', newIndex)

		// Update all name attributes — match the rule-level index in the name
		rule.querySelectorAll('[name]').forEach(element => {
			const name = element.getAttribute('name')
			// Channels page: channel[sync_rules][OLD] → channel[sync_rules][NEW]
			// Other screens: sync_rules[OLD] → sync_rules[NEW]
			element.setAttribute('name', name.replace(
				new RegExp(`\\[sync_rules\\]\\[${oldIndex}\\]|^sync_rules\\[${oldIndex}\\]`),
				(match) => match.replace(`[${oldIndex}]`, `[${newIndex}]`)
			))
		})

		// Update all id attributes
		rule.querySelectorAll('[id]').forEach(element => {
			const id = element.getAttribute('id')
			if (id.includes(oldIndex)) {
				let newId = id.replace(new RegExp(`-${oldIndex}$`), `-${newIndex}`)
				newId = newId.replace(`sync-rules-${oldIndex}-`, `sync-rules-${newIndex}-`)
				element.setAttribute('id', newId)
			}
		})

		// Update all for attributes
		rule.querySelectorAll('[for]').forEach(element => {
			const forAttr = element.getAttribute('for')
			if (forAttr.includes(oldIndex)) {
				let newFor = forAttr.replace(new RegExp(`-${oldIndex}$`), `-${newIndex}`)
				newFor = newFor.replace(`sync-rules-${oldIndex}-`, `sync-rules-${newIndex}-`)
				element.setAttribute('for', newFor)
			}
		})

	})
}

/**
 * Toggle the disabled-rule notice when a rule is enabled/disabled.
 */
delegationRoot.addEventListener('change', function(e) {
	if (e.target.classList.contains('buoyvs-rule-toggle')) {
		const notice = e.target.closest('.buoyvs-rule')?.querySelector('.buoyvs-rule-disabled-notice')
		if (notice) notice.classList.toggle('buoyvs-hidden', e.target.checked)
	}
})

/**
 * Show/hide the custom sync schedule number input and update the rule label.
 */
delegationRoot.addEventListener('change', function(e) {
	if (e.target.classList.contains('buoyvs-sync-schedule')) {
		const schedule = e.target.value
		const rule = e.target.closest('.buoyvs-rule')
		const wrapper = rule.querySelector('.buoyvs-custom-schedule-wrapper')
		if (wrapper) {
			wrapper.classList.toggle('buoyvs-hidden', schedule !== 'custom')
		}
		updateRuleLabel(rule)
	}
})

delegationRoot.addEventListener('input', function(e) {
	if (e.target.classList.contains('buoyvs-custom-sync-schedule')) {
		updateRuleLabel(e.target.closest('.buoyvs-rule'))
	}
})

/**
 * Update dynamic labels that depend on the selected action/resource.
 */
function updateRuleDynamicLabels(rule) {
	const action   = rule.querySelector('.buoyvs-action')?.value ?? ''
	const resource = rule.querySelector('.buoyvs-action')?.selectedOptions[0]?.dataset.resource ?? ''

	const maxItemsLabel = rule.querySelector('.buoyvs-max-items-label')
	if (maxItemsLabel) {
		const textNode = maxItemsLabel.firstChild
		const newText  = resource === 'video' ? 'Videos per run'
			: resource === 'playlist' ? 'Playlists per run'
			: 'Items per run'
		if (textNode && textNode.nodeType === Node.TEXT_NODE) {
			textNode.textContent = newText + ' '
		}
	}

	const postTypeLabel = rule.querySelector('.buoyvs-post-type-label')
	if (postTypeLabel) {
		const textNode = postTypeLabel.firstChild
		const newText  = action === 'playlists_sync_new'
			? 'Save synced playlists as post type'
			: action === 'channel_sync_new'
				? 'Save synced channel as post type'
				: 'Save synced videos as post type'
		if (textNode && textNode.nodeType === Node.TEXT_NODE) {
			textNode.textContent = newText + ' '
		}
	}
}

// Init dynamic labels on load
delegationRoot.querySelectorAll('.buoyvs-rule').forEach(updateRuleDynamicLabels)

/**
 * Apply correct visibility of post-type and taxonomy wrappers based on the selected action.
 */
function applyRuleActionVisibility(rule) {
	const action    = rule.querySelector('.buoyvs-action')?.value ?? ''
	const isSyncNew = action === 'videos_sync_new' || action === 'playlists_sync_new' || action === 'channel_sync_new'
	const postTypeWrapper = rule.querySelector('.buoyvs-post-type-wrapper')
	const postTypeSelect  = rule.querySelector('[name*="[destination_post_type]"]')
	if (postTypeWrapper) {
		postTypeWrapper.classList.toggle('buoyvs-hidden', !isSyncNew)
	}
	if (postTypeSelect) {
		postTypeSelect.disabled = !isSyncNew
	}
	rule.querySelector('.buoyvs-items-per-run-wrapper')?.classList.toggle('buoyvs-hidden', !!action && action.startsWith('channel_'))
}

// Initialize post-type visibility on load for all existing rules
delegationRoot.querySelectorAll('.buoyvs-rule').forEach(applyRuleActionVisibility)

/**
 * Show/hide a taxonomy-terms teaser based on whether the selected post type
 * has any public taxonomies registered (mirrors the server-rendered PHP condition).
 */
function applyTaxonomyVisibility(wrapper, postTypeSelect) {
	if (!wrapper || !postTypeSelect) return
	const opt  = postTypeSelect.selectedOptions[0]
	const show = !!opt?.value && opt.dataset.hasTaxonomy === '1'
	wrapper.classList.toggle('buoyvs-hidden', !show)
}

// Initialize taxonomy visibility on load (rule cards + channel Settings tab).
delegationRoot.querySelectorAll('.buoyvs-dest-post-type').forEach(function (select) {
	applyTaxonomyVisibility(select.closest('.buoyvs-rule')?.querySelector('.buoyvs-taxonomy-terms-wrapper'), select)
})
delegationRoot.querySelectorAll('.buoyvs-channel-default-post-type').forEach(function (select) {
	applyTaxonomyVisibility(select.closest('.buoyvs-channel-tab-panel')?.querySelector('.buoyvs-taxonomy-terms-wrapper'), select)
})

/**
 * Refresh action-dependent UI when a rule's action changes.
 */
delegationRoot.addEventListener('change', function(e) {

	if (e.target.classList.contains('buoyvs-action')) {
		const syncRule = e.target.closest('.buoyvs-rule')
		applyRuleActionVisibility(syncRule)
		updateRuleDynamicLabels(syncRule)
		updateRuleLabel(syncRule)
	}

	if (e.target.classList.contains('buoyvs-wizard-action-select')) {
		const wizard = e.target.closest('.buoyvs-wizard')
		if (!wizard) return
		const resource = e.target.selectedOptions[0]?.dataset.resource ?? ''
		const label = wizard.querySelector('.buoyvs-max-items-label')
		if (label) {
			const newText = resource === 'video'    ? 'Videos per run'
				: resource === 'playlist' ? 'Playlists per run'
				: 'Items per run'
			const tn = label.firstChild
			if (tn && tn.nodeType === Node.TEXT_NODE) tn.textContent = newText
			else label.textContent = newText
		}
		// Clear error state when user makes a selection.
		e.target.closest('.buoyvs-form-group')?.classList.remove('buoyvs-form-group--error')
		// Show/hide post type wrapper in step 3 based on action.
		const action         = e.target.value
		const isSyncNew       = action === 'videos_sync_new' || action === 'playlists_sync_new' || action === 'channel_sync_new'
		const isChannelAction = action.startsWith('channel_')
		wizard.querySelector('.buoyvs-wizard-post-type-wrapper')?.classList.toggle('buoyvs-hidden', !isSyncNew)
		wizard.querySelector('.buoyvs-items-per-run-wrapper')?.classList.toggle('buoyvs-hidden', isChannelAction)
		wizard.dataset.isChannelAction = isChannelAction ? '1' : ''
		updateWizardProgressIndicators(wizard)
	}

	if (e.target.classList.contains('buoyvs-dest-post-type')) {
		const syncRule = e.target.closest('.buoyvs-rule')
		applyTaxonomyVisibility(syncRule?.querySelector('.buoyvs-taxonomy-terms-wrapper'), e.target)
	}

	if (e.target.classList.contains('buoyvs-wizard-post-type')) {
		const wizard = e.target.closest('.buoyvs-wizard')
		applyTaxonomyVisibility(wizard?.querySelector('.buoyvs-taxonomy-terms-wrapper'), e.target)
	}

	if (e.target.classList.contains('buoyvs-channel-default-post-type')) {
		const panel = e.target.closest('.buoyvs-channel-tab-panel')
		applyTaxonomyVisibility(panel?.querySelector('.buoyvs-taxonomy-terms-wrapper'), e.target)
	}

	if (e.target.classList.contains('buoyvs-wizard-schedule-select')) {
		const wizard  = e.target.closest('.buoyvs-wizard')
		const wrapper = wizard?.querySelector('.buoyvs-wizard-custom-schedule-wrapper')
		if (wrapper) wrapper.classList.toggle('buoyvs-hidden', e.target.value !== 'custom')
	}
})

/**
 * Handle adding new sync rules.
 *
 * On the Channels page, clicking "Add sync rule" shows the wizard instead of
 * inserting a blank accordion card inline. On any other page, the old
 * behaviour (insert from template) is preserved.
 */
delegationRoot.addEventListener('click', function(e) {
	const btn = e.target.closest('.buoyvs-add-rule')
	if (!btn) return
	e.preventDefault()

	const channelGroup = btn.closest('.buoyvs-channel')

	// Channels page: show wizard.
	if (channelGroup && buoyvs.isChannelsPage) {
		const wizard = channelGroup.querySelector('.buoyvs-wizard')
		if (wizard) {
			wizard.classList.remove('buoyvs-hidden')
			wizardReset(wizard)
			wizard.scrollIntoView({ behavior: 'smooth', block: 'nearest' })
		}
		return
	}

	// Fallback: insert inline (single-channel page or no wizard present).
	const targetContainer = channelGroup
		? channelGroup.querySelector('.buoyvs-rules')
		: singleSyncRules

	if (!targetContainer) return

	const rules = [...targetContainer.querySelectorAll('.buoyvs-rule')]
	const newIndex = Math.max(-1, ...rules.map(r => +r.dataset.ruleIndex)) + 1
	let template = buoyvs.syncRule.rule
		.replaceAll('{{INDEX}}', newIndex)
		.replaceAll('{{NUMBER}}', newIndex + 1)

	targetContainer.insertAdjacentHTML('beforeend', template)
	applyRuleActionVisibility(targetContainer.lastElementChild)
	updateRuleDynamicLabels(targetContainer.lastElementChild)
	updateRuleLabel(targetContainer.lastElementChild)
	targetContainer.lastElementChild.scrollIntoView({ behavior: 'smooth', block: 'start' })
})

/**
 * Handle removal of sync rules
 */
delegationRoot.addEventListener('click', function(e) {
	if (e.target.classList.contains('buoyvs-remove-rule')) {
		if (!window.confirm('Delete this automation? This action cannot be undone.')) return

		const rule = e.target.closest('.buoyvs-rule')
		const rulesContainer = rule.closest('.buoyvs-rules')
		const totalRules = rulesContainer.querySelectorAll('.buoyvs-rule').length

		// If this is the last rule, clear the entire container
		if (totalRules === 1) {
			rulesContainer.innerHTML = ''
		} else {
			rule.remove()
			// Reindex remaining rules to ensure sequential numbering
			reindexRules(rulesContainer)
		}
	}
})

/**
 * Calculate and display estimated YouTube API quota cost for a sync rule.
 */
function updateQuotaEstimate(rule) {
	const el = rule.querySelector('.buoyvs-quota-estimate')
	if (!el) return

	const action      = rule.querySelector('.buoyvs-action')?.value || ''
	const schedule    = rule.querySelector('.buoyvs-sync-schedule')?.value || ''
	const customHours = parseInt(rule.querySelector('.buoyvs-custom-sync-schedule')?.value) || 24
	const videoCount  = parseInt(rule.closest('.buoyvs-rules')?.dataset.videoCount) || 0

	let perRun = null

	if (action === 'videos_sync_new') {
		const batches = Math.ceil(Math.max(1, videoCount) / 50)
		perRun = 1 + batches * 2
	} else if (action === 'playlists_sync_new') {
		el.textContent = 'Approx. 1 unit per 50 playlists'
		el.classList.remove('buoyvs-hidden')
		return
	} else {
		el.textContent = ''
		el.classList.add('buoyvs-hidden')
		return
	}

	let text = `Approx. ${perRun} unit${perRun !== 1 ? 's' : ''} per run`

	const multipliers = { hourly: 24, daily: 1, weekly: 1/7, monthly: 1/30 }
	const runsPerDay  = schedule === 'custom' ? 24 / customHours : (multipliers[schedule] ?? null)

	if (runsPerDay && runsPerDay >= 1) {
		text += ` · Approx. ${Math.round(perRun * runsPerDay)} units/day`
	}

	el.textContent = text
	el.classList.remove('buoyvs-hidden')
}

delegationRoot.querySelectorAll('.buoyvs-rule').forEach(updateQuotaEstimate)


delegationRoot.addEventListener('change', function(e) {
	if (
		e.target.classList.contains('buoyvs-action') ||
		e.target.classList.contains('buoyvs-sync-schedule') ||
		e.target.classList.contains('buoyvs-custom-sync-schedule')
	) {
		updateQuotaEstimate(e.target.closest('.buoyvs-rule'))
	}
})

// ============================================================
// Sync Rule Wizard (flat scope inside delegationRoot for function access)
// ============================================================
const TOTAL_STEPS    = 3
const SLIDE_DURATION = 220 // ms

/**
 * Update the progress bar to show only steps the user will actually visit,
 * renumbering them sequentially (1, 2, 3…) so there are no gaps.
 */
function updateWizardProgressIndicators(wizard) {
	let displayNum = 1
	wizard.querySelectorAll('.buoyvs-wizard-step-indicator').forEach(indicator => {
		indicator.classList.remove('buoyvs-hidden')
		const prevLine = indicator.previousElementSibling
		if (prevLine?.classList.contains('buoyvs-wizard-progress-line')) {
			prevLine.classList.remove('buoyvs-hidden')
		}
		indicator.textContent = displayNum++
	})
}

/**
 * Reset the wizard to step 1.
 */
function wizardReset(wizard) {
	wizard.querySelector('.buoyvs-items-per-run-wrapper')?.classList.remove('buoyvs-hidden')
	updateWizardProgressIndicators(wizard)
	wizardShowStep(wizard, 1)
	// Clear error messages.
	wizard.querySelectorAll('.buoyvs-wizard-error').forEach(el => el.classList.add('buoyvs-hidden'))
	wizard.querySelector('.buoyvs-wizard-finish')?.removeAttribute('disabled')
	// Restore default post type from channel settings.
	const defaultPostType = wizard.dataset.defaultPostType
	const ptSelect = wizard.querySelector('.buoyvs-wizard-post-type')
	if (ptSelect && defaultPostType) ptSelect.value = defaultPostType
}

/**
 * Show a specific step panel and update progress indicators.
 */
function wizardShowStep(wizard, step, direction = 'none') {
	const panels  = wizard.querySelector('.buoyvs-wizard-panels')
	const current = panels?.querySelector('.buoyvs-wizard-panel:not(.buoyvs-hidden)') ?? null
	const next    = wizard.querySelector(`.buoyvs-wizard-panel[data-step="${step}"]`)

	if (!next) return

	// Update dataset + progress indicators immediately.
	wizard.dataset.currentStep = step
	wizard.querySelectorAll('.buoyvs-wizard-step-indicator').forEach(dot => {
		const dotStep = +dot.dataset.step
		dot.classList.toggle('buoyvs-wizard-step-indicator--active', dotStep === step)
		dot.classList.toggle('buoyvs-wizard-step-indicator--done', dotStep < step)
	})

	// Skip animation if: no wrapper, no outgoing panel, same panel, or no direction given.
	if (!panels || !current || current === next || direction === 'none') {
		wizard.querySelectorAll('.buoyvs-wizard-panel').forEach(p => p.classList.toggle('buoyvs-hidden', p !== next))
		return
	}

	// Guard against rapid clicks during animation.
	if (wizard.dataset.animating) return
	wizard.dataset.animating = '1'

	// Freeze wrapper height so the container doesn't resize mid-transition.
	panels.style.height   = current.offsetHeight + 'px'
	panels.style.overflow = 'hidden'
	panels.style.position = 'relative'

	// Pin both panels absolutely so they occupy the same space.
	const pin = (panel, x) => {
		panel.style.position  = 'absolute'
		panel.style.top       = '0'
		panel.style.left      = '0'
		panel.style.width     = '100%'
		panel.style.transform = `translateX(${x}%)`
	}

	next.classList.remove('buoyvs-hidden')
	pin(current, 0)
	pin(next, direction === 'forward' ? 100 : -100)

	// Force reflow so initial transforms are painted before transition starts.
	next.getBoundingClientRect()

	const tr = `transform ${SLIDE_DURATION}ms ease`
	current.style.transition = tr
	next.style.transition    = tr

	current.style.transform = `translateX(${direction === 'forward' ? -100 : 100}%)`
	next.style.transform    = 'translateX(0%)'

	setTimeout(() => {
		const unpin = p => {
			p.style.position   = ''
			p.style.top        = ''
			p.style.left       = ''
			p.style.width      = ''
			p.style.transform  = ''
			p.style.transition = ''
		}
		unpin(current)
		unpin(next)
		current.classList.add('buoyvs-hidden')
		panels.style.height   = ''
		panels.style.overflow = ''
		panels.style.position = ''
		delete wizard.dataset.animating
	}, SLIDE_DURATION + 20)
}

/**
 * Validate the current step. Returns true if valid.
 */
function wizardValidateStep(wizard, step) {
	if (step === 1) {
		const actionSelect = wizard.querySelector('.buoyvs-wizard-action-select')
		if (!actionSelect?.value) {
			actionSelect?.closest('.buoyvs-form-group')?.classList.add('buoyvs-form-group--error')
			return false
		}
		actionSelect.closest('.buoyvs-form-group')?.classList.remove('buoyvs-form-group--error')
	}
	return true
}

/**
 * Advance to the next step.
 */
function wizardAdvance(wizard, fromStep) {
	if (!wizardValidateStep(wizard, fromStep)) return

	// After Step 1: show/hide post type wrapper in Step 3 based on action.
	if (fromStep === 1) {
		const action = wizard.querySelector('.buoyvs-wizard-action-select')?.value ?? ''
		const isSyncNew = action === 'videos_sync_new' || action === 'playlists_sync_new' || action === 'channel_sync_new'
		wizard.querySelector('.buoyvs-wizard-post-type-wrapper')?.classList.toggle('buoyvs-hidden', !isSyncNew)
	}

	const next = fromStep + 1
	if (next > TOTAL_STEPS) return
	wizardShowStep(wizard, next, 'forward')
}

/**
 * Go back one step.
 */
function wizardBack(wizard, fromStep) {
	const prev = fromStep - 1
	if (prev < 1) return
	wizardShowStep(wizard, prev, 'back')
}

/**
 * Build the rule object from wizard inputs.
 */
function collectWizardRule(wizard) {
	const action      = wizard.querySelector('.buoyvs-wizard-action-select')?.value ?? ''
	const schedule    = wizard.querySelector('.buoyvs-wizard-schedule-select')?.value ?? 'daily'
	const customSched = parseInt(wizard.querySelector('.buoyvs-wizard-custom-schedule')?.value) || 24
	const maxVideos   = parseInt(wizard.querySelector('.buoyvs-wizard-max-videos')?.value) || 0
	const postType    = wizard.querySelector('.buoyvs-wizard-post-type')?.value ?? ''
	const postStatus  = wizard.querySelector('.buoyvs-wizard-post-status')?.value ?? 'publish'

	return {
		action,
		schedule,
		custom_schedule:       customSched,
		max_videos:            maxVideos,
		destination_post_type: postType,
		post_status:           postStatus,
	}
}

/**
 * Submit the wizard via AJAX and insert the resulting accordion card.
 */
function wizardSubmit(wizard) {
	const rule = collectWizardRule(wizard)

	const finishBtn = wizard.querySelector('.buoyvs-wizard-finish')
	if (finishBtn) finishBtn.setAttribute('disabled', 'disabled')

	const errEl = wizard.querySelector('[data-step="3"] .buoyvs-wizard-error')

	// Build FormData — jQuery serializes nested objects correctly.
	const data = {
		action: 'buoyvs_add_rule',
		nonce:  buoyvs.addRuleNonce,
		rule:   rule,
	}

	jQuery.ajax({
		url:      buoyvs.ajaxUrl,
		method:   'POST',
		data:     data,
		dataType: 'json',
	}).done(function(response) {
		if (!response.success) {
			if (errEl) {
				errEl.textContent = response.data || 'An error occurred. Please try again.'
				errEl.classList.remove('buoyvs-hidden')
			}
			if (finishBtn) finishBtn.removeAttribute('disabled')
			return
		}

		// Insert the accordion card into the rules container.
		const channelGroup = wizard.closest('.buoyvs-channel')
		const rulesContainer = channelGroup?.querySelector('.buoyvs-rules')
		if (rulesContainer && response.data.html) {
			rulesContainer.insertAdjacentHTML('beforeend', response.data.html)
			const newRule = rulesContainer.lastElementChild
			if (newRule) {
				applyRuleActionVisibility(newRule)
				updateRuleDynamicLabels(newRule)
				updateRuleLabel(newRule)
				// An enabled once rule runs immediately on save — start polling its
				// progress (the load-time scan won't see this freshly-inserted card).
				if (newRule.classList.contains('buoyvs-rule--syncing')) {
					newRule.dispatchEvent(new CustomEvent('buoyvs:poll-rule', { bubbles: true }))
				}
				newRule.scrollIntoView({ behavior: 'smooth', block: 'start' })
			}
		}

		// Hide wizard, restore button.
		wizardClose(wizard)

	}).fail(function() {
		if (errEl) {
			errEl.textContent = 'A network error occurred. Please try again.'
			errEl.classList.remove('buoyvs-hidden')
		}
		if (finishBtn) finishBtn.removeAttribute('disabled')
	})
}

/**
 * Close the wizard and restore the "Add sync rule" button.
 */
function wizardClose(wizard) {
	wizard.classList.add('buoyvs-hidden')
}

// ---- Event delegation for all wizard interactions (channels page only) ----
if (buoyvs.isChannelsPage) {
delegationRoot.addEventListener('click', function(e) {

	// Cancel
	if (e.target.closest('.buoyvs-wizard-cancel')) {
		const wizard = e.target.closest('.buoyvs-wizard')
		if (wizard) wizardClose(wizard)
		return
	}

	// Next
	const nextBtn = e.target.closest('.buoyvs-wizard-next')
	if (nextBtn) {
		const wizard = nextBtn.closest('.buoyvs-wizard')
		if (wizard) wizardAdvance(wizard, +nextBtn.dataset.step)
		return
	}

	// Back
	const backBtn = e.target.closest('.buoyvs-wizard-back')
	if (backBtn) {
		const wizard = backBtn.closest('.buoyvs-wizard')
		if (wizard) wizardBack(wizard, +backBtn.dataset.step)
		return
	}

	// Finish (final step submit)
	if (e.target.closest('.buoyvs-wizard-finish')) {
		const wizard = e.target.closest('.buoyvs-wizard')
		if (wizard) wizardSubmit(wizard)
		return
	}
})

} // end if (buoyvs.isChannelsPage) wizard events

} // end if (delegationRoot)


/**
 * Channels page — Accordion toggle, Add Channel, Remove Channel
 */
;(function() {
	const container = document.getElementById('buoyvs-channels')
	if (!container) return

	/**
	 * Toggle error state on a Channel ID input based on whether it is empty.
	 */
	function updateChannelIdState(input) {
		input.classList.toggle('buoyvs-error', input.value.trim() === '')
	}

	// Clear error state as the user types a Channel ID.
	container.addEventListener('input', function(e) {
		if (e.target.matches('input[name$="[youtube_id]"]')) {
			updateChannelIdState(e.target)
		}
	})

	// Validate Channel ID inputs on form submit.
	const channelsForm = container.closest('form')
	if (channelsForm) {
		channelsForm.noValidate = true
		channelsForm.addEventListener('submit', function(e) {
			let hasError = false
			container.querySelectorAll('input[name$="[youtube_id]"]').forEach(function(input) {
				updateChannelIdState(input)
				if (input.value.trim() === '') hasError = true
			})
			if (hasError) {
				e.preventDefault()
				container.querySelector('input[name$="[youtube_id]"].buoyvs-error')?.scrollIntoView({ behavior: 'smooth', block: 'center' })
			}
		}, true)
	}

	// Delete Channel — clears the single flat channel via a dedicated flag.
	// form.submit() (unlike a normal click or .requestSubmit()) does not fire
	// the 'submit' event at all, so it bypasses the Channel ID validation
	// above — there's nothing to validate when deleting the channel.
	// submit_button() renders <input name="submit">, which shadows the form's
	// submit() method, so it must be invoked via the prototype.
	container.addEventListener('click', function(e) {
		const btn = e.target.closest('.buoyvs-remove-channel')
		if (!btn) return
		e.preventDefault()

		if (!window.confirm('Are you sure you want to delete this channel? All its sync rules will be removed.')) return
		if (!channelsForm) return

		let flag = channelsForm.querySelector('input[name="buoyvs_delete_channel"]')
		if (!flag) {
			flag = document.createElement('input')
			flag.type = 'hidden'
			flag.name = 'buoyvs_delete_channel'
			channelsForm.appendChild(flag)
		}
		flag.value = '1'

		HTMLFormElement.prototype.submit.call(channelsForm)
	})

	/**
	 * Toggle accordion expand/collapse on a channel group header.
	 */
	function chCollapseKey(group) {
		const ytInput = group.querySelector('input[name$="[youtube_id]"]')
		const ytId = ytInput ? ytInput.value.trim() : ''
		return ytId ? `buoyvs_ch_open_${ytId}` : `buoyvs_ch_open_idx_${group.dataset.channelIndex}`
	}

	function toggleAccordion(header) {
		const group = header.closest('.buoyvs-channel')
		if (!group) return
		const isExpanded = header.getAttribute('aria-expanded') === 'true'
		header.setAttribute('aria-expanded', isExpanded ? 'false' : 'true')
		group.classList.toggle('buoyvs-collapsed', isExpanded)
		try { localStorage.setItem(chCollapseKey(group), isExpanded ? '0' : '1') } catch (ex) {}
	}

	container.addEventListener('click', function(e) {
		const header = e.target.closest('.buoyvs-channel-header')
		if (header && !e.target.closest('button')) {
			toggleAccordion(header)
		}
	})

	container.addEventListener('keydown', function(e) {
		if (e.key !== 'Enter' && e.key !== ' ') return
		const header = e.target.closest('.buoyvs-channel-header')
		if (header) {
			e.preventDefault()
			toggleAccordion(header)
		}
	})


	// Restore persisted collapse state on load.
	container.querySelectorAll('.buoyvs-channel').forEach(group => {
		try {
			const stored = localStorage.getItem(chCollapseKey(group))
			if (stored === '0') {
				group.classList.add('buoyvs-collapsed')
				const h = group.querySelector('.buoyvs-channel-header')
				if (h) h.setAttribute('aria-expanded', 'false')
			}
		} catch (ex) {}
	})
})()

// ============================================================
// Channel card vertical tabs
// ============================================================
;(function () {
	const container = document.getElementById('buoyvs-channels')
	if (!container) return

	const DEFAULT_TAB = 'info'
	const storageKey  = (idx) => `buoyvs_ch_tab_${idx}`

	function activateTab(card, tab) {
		card.querySelectorAll('.buoyvs-channel-tab-btn').forEach(b => {
			const on = b.dataset.tab === tab
			b.classList.toggle('buoyvs-channel-tab-btn--active', on)
			b.setAttribute('aria-selected', String(on))
		})
		card.querySelectorAll('.buoyvs-channel-tab-panel').forEach(p => {
			p.classList.toggle('buoyvs-hidden', p.dataset.panel !== tab)
		})
		if (tab === 'history') {
			const badge = card.querySelector('.buoyvs-channel-tab-btn[data-tab="history"] .buoyvs-history-badge')
			if (badge) {
				badge.remove()
				const youtubeId = card.dataset.youtubeId
				if (youtubeId && buoyvs.markHistoryReadNonce) {
					const fd = new FormData()
					fd.append('action', 'buoyvs_mark_history_read')
					fd.append('nonce', buoyvs.markHistoryReadNonce)
					fd.append('youtube_id', youtubeId)
					navigator.sendBeacon(buoyvs.ajaxUrl, fd)
				}
			}
		}
	}

	// Restore persisted active tab on load.
	container.querySelectorAll('.buoyvs-channel').forEach(card => {
		const saved = localStorage.getItem(storageKey(card.dataset.channelIndex)) || DEFAULT_TAB
		activateTab(card, saved)
	})

	// Delegate tab clicks.
	container.addEventListener('click', e => {
		const btn = e.target.closest('.buoyvs-channel-tab-btn')
		if (!btn) return
		const card = btn.closest('.buoyvs-channel')
		const tab  = btn.dataset.tab
		activateTab(card, tab)
		try { localStorage.setItem(storageKey(card.dataset.channelIndex), tab) } catch (ex) {}
	})

})()


/**
 * Unsaved changes warning — warn before navigating away with pending edits.
 */
;(function() {
	const form = document.querySelector('#buoyvs-channels')?.closest('form')
	if (!form) return

	let isDirty = false

	// Track value changes
	form.addEventListener('input', function() { isDirty = true })
	form.addEventListener('change', function() { isDirty = true })

	// Track structural changes (channels/rules added or removed).
	// Ignore mutations triggered by the sync-progress polling (programmatic updates).
	const observer = new MutationObserver(function() { if (!window._buoyvsSyncUpdate) isDirty = true })
	observer.observe(form, { childList: true, subtree: true })

	// Clear on submit so the save redirect doesn't trigger the warning
	form.addEventListener('submit', function() {
		isDirty = false
		observer.disconnect()
	})

	window.addEventListener('beforeunload', function(e) {
		if (!isDirty) return
		e.preventDefault()
		e.returnValue = ''
	})
})()

/**
 * Infinity icon for max-videos inputs.
 * Shows the icon when value is 0 or empty (= unlimited); hides on click and focuses input.
 */
;(function () {
	function checkUnlimited(input) {
		const icon = input.parentNode.querySelector('.buoyvs-unlimited-icon')
		if (!icon) return
		if (!input.value || input.value === '0') {
			icon.classList.remove('buoyvs-hidden')
		}
	}

	document.addEventListener('input', function (e) {
		if (e.target.classList.contains('buoyvs-max-videos-input')) {
			const icon = e.target.parentNode.querySelector('.buoyvs-unlimited-icon')
			if (!icon) return
			if (e.target.value && e.target.value !== '0') {
				icon.classList.add('buoyvs-hidden')
			} else {
				icon.classList.remove('buoyvs-hidden')
			}
		}
	})

	document.addEventListener('blur', function (e) {
		if (e.target.classList.contains('buoyvs-max-videos-input')) {
			checkUnlimited(e.target)
		}
	}, true)

	document.addEventListener('click', function (e) {
		const icon = e.target.closest('.buoyvs-unlimited-icon')
		if (!icon) return
		icon.style.display = 'none'
		const input = icon.parentNode.querySelector('.buoyvs-max-videos-input')
		if (input) {
			input.value = ''
			input.focus()
		}
	})
}())

/**
 * Sync progress polling.
 * On page load, finds any .buoyvs-rule--syncing cards and polls the server
 * every 2.5s for progress. Updates the badge text and dismisses the overlay
 * when the sync completes.
 */
;(function () {
	const container = document.getElementById('buoyvs-channels')
	if (!container) return

	const POLL_MS   = 2500
	const activePolls = new Map() // key: "ch_rule" → intervalId

	// Start polling any rules already syncing at page load.
	container.querySelectorAll('.buoyvs-rule--syncing').forEach(function (ruleEl) {
		startPollingRule(ruleEl)
	})

	// Start polling a rule that started syncing after load (e.g. added via the wizard).
	container.addEventListener('buoyvs:poll-rule', function (e) {
		const ruleEl = e.target.closest('.buoyvs-rule')
		if (ruleEl) startPollingRule(ruleEl)
	})

	function startPollingRule(ruleEl) {
		const ruleIndex = ruleEl.dataset.ruleIndex
		if (ruleIndex === undefined) return

		const key = ruleIndex
		if (activePolls.has(key)) return

		poll(ruleIndex, ruleEl, key)
		const id = setInterval(function () {
			poll(ruleIndex, ruleEl, key)
		}, POLL_MS)
		activePolls.set(key, id)
	}

	function stopPolling(key) {
		if (activePolls.has(key)) {
			clearInterval(activePolls.get(key))
			activePolls.delete(key)
		}
	}

	function poll(ruleIndex, ruleEl, key) {
		const body = new URLSearchParams({
			action:     'buoyvs_sync_progress',
			nonce:      buoyvs.syncProgressNonce,
			rule_index: ruleIndex,
		})

		fetch(buoyvs.ajaxUrl, {
			method:  'POST',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body:    body.toString(),
		})
		.then(function (r) { return r.json() })
		.then(function (res) {
			if (!res.success) { stopPolling(key); return }

			const data = res.data
			if (data.status === 'syncing') {
				updateBadge(ruleEl, data.current, data.total)
			} else {
				stopPolling(key)
				onSyncDone(ruleEl, data)
			}
		})
		.catch(function () { stopPolling(key) })
	}

	function updateBadge(ruleEl, current, total) {
		const progress = ruleEl.querySelector('.buoyvs-syncing-progress')
		if (!progress) return
		progress.textContent = total > 0 ? (' ' + current + ' of ' + total) : ''
	}

	function onSyncDone(ruleEl, data) {
		window._buoyvsSyncUpdate = true

		// Show final progress count in the overlay before removing it.
		if (data.total > 0) {
			updateBadge(ruleEl, data.synced, data.total)
		}

		function finish() {
			// Remove overlay and syncing state.
			ruleEl.classList.remove('buoyvs-rule--syncing')
			const overlay = ruleEl.querySelector('.buoyvs-syncing-overlay')
			if (overlay) overlay.remove()

			// If the rule was auto-disabled after its run, reflect that in the UI.
			if (data.enabled === false) {
				const toggle = ruleEl.querySelector('.buoyvs-rule-toggle')
				if (toggle) toggle.checked = false
				const headingWrap = ruleEl.querySelector('.buoyvs-rule-heading-wrap')
				if (headingWrap && !headingWrap.querySelector('.buoyvs-rule-disabled-notice')) {
					const notice = document.createElement('p')
					notice.className = 'buoyvs-rule-disabled-notice'
					notice.textContent = "This rule is disabled and won't run until re-enabled."
					headingWrap.appendChild(notice)
				}
			}

			setTimeout(function () { window._buoyvsSyncUpdate = false }, 0)

			// Update "Last Sync" in the sync-info tooltip if present.
			if (data.last_synced_label) {
				const tooltip  = ruleEl.querySelector('.buoyvs-sync-info-tooltip')
				if (tooltip) {
					let lastSyncItem = Array.from(tooltip.querySelectorAll('.buoyvs-sync-info-item')).find(function (el) {
						return el.textContent.indexOf('Last Sync') !== -1
					})
					if (lastSyncItem) {
						const spans = lastSyncItem.querySelectorAll('span')
						if (spans[1]) spans[1].textContent = data.last_synced_label
					} else {
						// Insert "Last Sync" item before the first item.
						const item = document.createElement('span')
						item.className = 'buoyvs-sync-info-item'
						item.innerHTML = '<span>Last Sync:</span><span>' + data.last_synced_label + '</span>'
						tooltip.insertBefore(item, tooltip.firstChild)
					}
					// Make sure the sync-info button is visible.
					const syncInfoBtn = ruleEl.querySelector('.buoyvs-sync-info')
					if (syncInfoBtn) syncInfoBtn.hidden = false
				}
			}
		}

		// Brief display of final count before dismissing the overlay.
		if (data.total > 0) {
			setTimeout(finish, 1200)
		} else {
			finish()
		}
	}
}())

;(function () {
	document.addEventListener('click', function (e) {
		const btn = e.target.closest('.buoyvs-history-entry-toggle')
		if (!btn) return
		const entry  = btn.closest('.buoyvs-history-entry')
		const errors = entry && entry.querySelector('.buoyvs-history-entry-errors')
		if (!errors) return
		const expanded = btn.getAttribute('aria-expanded') === 'true'
		errors.classList.toggle('buoyvs-hidden', expanded)
		btn.setAttribute('aria-expanded', String(!expanded))
		const icon = btn.querySelector('.material-icons-outlined')
		if (icon) icon.textContent = expanded ? 'expand_more' : 'expand_less'
	})
})()



// ============================================================
// Help tooltips — click to open, click outside to close
// ============================================================
;(function () {
	// <label> elements containing a help button forward clicks on the label text
	// to the button. Block that by calling preventDefault() in the capture phase
	// whenever the click target is not the button itself.
	document.addEventListener('click', function (e) {
		const label = e.target.closest('label')
		if (label && label.querySelector('.buoyvs-help-btn') && !e.target.closest('.buoyvs-help-btn')) {
			e.preventDefault()
		}
	}, true)

	document.addEventListener('click', function (e) {
		const btn  = e.target.closest('.buoyvs-help-btn')
		const wrap = btn ? btn.closest('.buoyvs-help-wrap') : null
		const wasOpen = wrap ? wrap.classList.contains('buoyvs-help-wrap--open') : false

		document.querySelectorAll('.buoyvs-help-wrap--open').forEach(function (w) {
			w.classList.remove('buoyvs-help-wrap--open')
		})

		if (wrap && !wasOpen) {
			wrap.classList.add('buoyvs-help-wrap--open')
		}
	})
})()
