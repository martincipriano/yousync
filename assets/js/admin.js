const syncRules = document.getElementById('ys-sync-rules')

/**
 * Reindex all sync rules to ensure sequential numbering (0, 1, 2...)
 */
function reindexRules() {
	const rules = syncRules.querySelectorAll('.ys-sync-rule')
	rules.forEach((rule, newIndex) => {
		const oldIndex = rule.getAttribute('data-rule-index')

		// Update data attribute
		rule.setAttribute('data-rule-index', newIndex)

		// Update all name attributes
		rule.querySelectorAll('[name]').forEach(element => {
			const name = element.getAttribute('name')
			element.setAttribute('name', name.replace(`sync_rules[${oldIndex}]`, `sync_rules[${newIndex}]`))
		})

		// Update all id attributes
		rule.querySelectorAll('[id]').forEach(element => {
			const id = element.getAttribute('id')
			if (id.includes(oldIndex)) {
				element.setAttribute('id', id.replace(new RegExp(oldIndex, 'g'), newIndex))
			}
		})

		// Update all for attributes
		rule.querySelectorAll('[for]').forEach(element => {
			const forAttr = element.getAttribute('for')
			if (forAttr.includes(oldIndex)) {
				element.setAttribute('for', forAttr.replace(new RegExp(oldIndex, 'g'), newIndex))
			}
		})

		// Update conditions container data-rule-index
		const conditionsContainer = rule.querySelector('.ys-conditions')
		if (conditionsContainer) {
			conditionsContainer.setAttribute('data-rule-index', newIndex)
		}
	})
}

/**
 * Reindex all conditions within a specific rule to ensure sequential numbering (0, 1, 2...)
 */
function reindexConditions(rule) {
	const conditions = rule.querySelectorAll('.ys-condition')

	conditions.forEach((condition, newConditionIndex) => {
		// Update all name attributes
		condition.querySelectorAll('[name*="[conditions]"]').forEach(element => {
			const name = element.getAttribute('name')
			const match = name.match(/\[conditions\]\[(\d+)\]/)
			if (match) {
				const oldConditionIndex = match[1]
				element.setAttribute('name', name.replace(
					`[conditions][${oldConditionIndex}]`,
					`[conditions][${newConditionIndex}]`
				))
			}
		})

		// Update all id attributes
		condition.querySelectorAll('[id]').forEach(element => {
			const id = element.getAttribute('id')
			const match = id.match(/conditions-(\d+)-/)
			if (match) {
				const oldConditionIndex = match[1]
				element.setAttribute('id', id.replace(
					`conditions-${oldConditionIndex}-`,
					`conditions-${newConditionIndex}-`
				))
			}
		})

		// Update all for attributes
		condition.querySelectorAll('[for]').forEach(element => {
			const forAttr = element.getAttribute('for')
			const match = forAttr.match(/conditions-(\d+)-/)
			if (match) {
				const oldConditionIndex = match[1]
				element.setAttribute('for', forAttr.replace(
					`conditions-${oldConditionIndex}-`,
					`conditions-${newConditionIndex}-`
				))
			}
		})
	})
}

/**
 * Script to enable/disable custom sync schedule number input
 */
syncRules.addEventListener('change', function(e) {
	if (e.target.classList.contains('ys-sync-schedule')) {
		const schedule = e.target.value
		const rule = e.target.closest('.ys-sync-rule')
		const customSyncSchedule = rule.querySelector('.ys-custom-sync-schedule')
		if (schedule === 'custom') {
			customSyncSchedule.removeAttribute('disabled')	
		} else {
			customSyncSchedule.setAttribute('disabled', 'disabled')
		}
	}
})

/**
 * Update specific metadata options based on the rule action selected
 */
syncRules.addEventListener('change', function(e) {

	if (e.target.classList.contains('ys-action')) {
		const syncRule	= e.target.closest('.ys-sync-rule')
		const formGroup	= syncRule.querySelector('.ys-specific-metadata-wrapper')
		const select		= syncRule.querySelector('.ys-specific-metadata')
		const value			= e.target.value
		const selected	= e.target.selectedOptions[0]
		const resource	= selected.dataset.resource

		// Display metadata field input only when "update_specific_metadata" action is selected
		if (value.includes('update_specific')) {

			// Check if Tom Select is initialized on this select element
			if (select.tomselect) {
				const tomselect = select.tomselect

				// Clear the options and the selection before replacing the options
				tomselect.clear()
				tomselect.clearOptions()

				// Display resource specific options
				select.innerHTML = youSync.syncRule[resource].fieldOptions
				select.querySelectorAll('option').forEach(option => {

					// Skip disabled options
					if (!option.disabled) {
						tomselect.addOption({
							value: option.value,
							text: option.textContent
						})
					}
				})

				// Refresh the UI input without triggering change event
				tomselect.refreshOptions(false)
			} else {

				// Tom Select hasn't been initialized yet
				select.innerHTML = youSync.syncRule[resource].fieldOptions

				// Initialize Tom Select
				new TomSelect(select, {
					closeAfterSelect: false,
					hideSelected: true,
					maxOptions: null,
					placeholder: 'Select metadata to update',
					plugins: ['remove_button']
				})
			}
			formGroup.classList.remove('ys-hidden')
		} else {
			formGroup.classList.add('ys-hidden')
		}
	}
})

/**
 * Update conditions based on the selected action
 */
syncRules.addEventListener('change', function(e) {

	if (e.target.classList.contains('ys-action')) {
		const syncRule					= e.target.closest('.ys-sync-rule')
		const conditions				= syncRule.querySelector('.ys-conditions')
		const selectedAction		= e.target.selectedOptions[0]
		const selectedResource	= selectedAction.dataset.resource

		/**
		 * Only reset condition fields when the resource type changes
		 * E.g., switching from "Sync new videos" to "Sync new playlists" (different resources) resets conditions
		 * But switching from "Sync new videos" to "Update all videos" (same resource) preserves conditions
		 * This prevents users from losing their work when toggling between actions of the same resource
		 */
		if (selectedResource != conditions.dataset.resource) {
			const conditionGroups	= syncRule.querySelectorAll('.ys-condition')
			const conditionField = syncRule.querySelector('.ys-condition-field')
			const operatorField = syncRule.querySelector('.ys-condition-operator')
			const valueField = syncRule.querySelector('.ys-condition-value')

			/**
			 * Store the resource in a data attribute
			 * This will be sed later for comparison
			 */
			conditions.dataset.resource = selectedResource

			/**
			 * Keep only the first condition when resource changes
			 * Other conditions are removed because their field/operator/value selections are invalid for the new resource type
			 */
			conditionGroups.forEach((conditionGroup, index) => {
				if (index != 0) {
					conditionGroup.remove()
				}
			})

			/**
			 * Update the condition field if it exists
			 */
			if (conditionField) {
				conditionField.innerHTML = youSync.syncRule[selectedResource].fieldOptions
			}
			
			/**
			 * Update the operator if it exists
			 */
			if (operatorField) {
				operatorField.innerHTML = ''
				operatorField.disabled = true
			}

			if (valueField) {
				valueField.insertAdjacentHTML('beforebegin', youSync.values.text)
				valueField.remove()
			}
		}
	}
})

/**
 * Update the conditions's operator and value inputs based on the selected field
 */
syncRules.addEventListener('change', function(e) {
	if (e.target.classList.contains('ys-condition-field')) {
		const field = e.target.value
		const condition = e.target.closest('.ys-condition')
		const operatorSelect = condition.querySelector('.ys-condition-operator')
		const valueInput = condition.querySelector('.ys-condition-value')

		if (!field) {
			operatorSelect.innerHTML = ''
			operatorSelect.value = ''
			operatorSelect.disabled = true
			return
		}

		// Extract rule and condition indices
		const rule = condition.closest('.ys-sync-rule')
		const ruleIndex = rule.getAttribute('data-rule-index')

		// Extract condition index from the existing input's name attribute
		const nameAttr = valueInput.getAttribute('name')
		const conditionMatch = nameAttr.match(/\[conditions\]\[(\d+)\]/)
		const conditionIndex = conditionMatch ? conditionMatch[1] : '0'

		// Get the data-type of the selected field
		const selectedOption = e.target.querySelector(`option[value="${field}"]`)
		const fieldType = selectedOption ? selectedOption.getAttribute('data-type') : ''

		// Populate operator optgroups based on field type
		if (fieldType === 'text' && youSync.operators.text) {
			operatorSelect.innerHTML = youSync.operators.text
			const updatedTemplate = youSync.values.text.replace('disabled', '')
				.replaceAll('{{RULE_INDEX}}', ruleIndex)
				.replaceAll('{{CONDITION_INDEX}}', conditionIndex)
			valueInput.insertAdjacentHTML('beforebegin', updatedTemplate)
			valueInput.remove()
		} else if (fieldType === 'number' && youSync.operators.number) {
			operatorSelect.innerHTML = youSync.operators.number
			const updatedTemplate = youSync.values.number
				.replace('disabled', '')
				.replaceAll('{{RULE_INDEX}}', ruleIndex)
				.replaceAll('{{CONDITION_INDEX}}', conditionIndex)
			valueInput.insertAdjacentHTML('beforebegin', updatedTemplate)
			valueInput.remove()
		} else if (fieldType === 'date' && youSync.operators.date) {
			operatorSelect.innerHTML = youSync.operators.date
			const updatedTemplate = youSync.values.date
				.replace('disabled', '')
				.replaceAll('{{RULE_INDEX}}', ruleIndex)
				.replaceAll('{{CONDITION_INDEX}}', conditionIndex)
			valueInput.insertAdjacentHTML('beforebegin', updatedTemplate)
			valueInput.remove()
		} else {
			operatorSelect.innerHTML = ''
			operatorSelect.value = ''
		}

		operatorSelect.disabled = false
	}
})








/**
 * Handle adding new conditions
 */
syncRules.addEventListener('click', function(e) {
	if (e.target.classList.contains('ys-add-condition')) {
		e.preventDefault()

		const rule = e.target.closest('.ys-sync-rule')
		const ruleIndex = rule.getAttribute('data-rule-index')
		const conditionsContainer = rule.querySelector('.ys-conditions')

		// Get current highest condition index
		const existingConditions = conditionsContainer.querySelectorAll('.ys-condition')
		let maxIndex = -1
		existingConditions.forEach(condition => {
			// Extract condition index from name attribute
			const nameAttr = condition.querySelector('[name*="[conditions]"]')
			if (nameAttr) {
				const match = nameAttr.getAttribute('name').match(/\[conditions\]\[(\d+)\]/)
				if (match) {
					const index = parseInt(match[1])
					if (index > maxIndex) maxIndex = index
				}
			}
		})

		// New condition index
		const newConditionIndex = maxIndex + 1

		// Replace placeholders with actual indices
		let newConditionHTML = youSync.syncRule.condition
			.replaceAll('{{RULE_INDEX}}', ruleIndex)
			.replaceAll('{{CONDITION_INDEX}}', newConditionIndex)

		// Append new condition to the container
		conditionsContainer.insertAdjacentHTML('beforeend', newConditionHTML)

		// Populate field options based on currently selected action
		const actionSelect = rule.querySelector('.ys-action')
		const action = actionSelect ? actionSelect.value : ''

		let fieldOptions = ''
		
		if (['channel_update_specific', 'channel_update_all'].includes(action)) {
			fieldOptions = youSync.syncRule.channel.fieldOptions
		} else if (['playlists_sync_new', 'playlists_update_all', 'playlists_update_non_modified', 'playlists_update_specific_all', 'playlists_update_specific_non_modified'].includes(action)) {
			fieldOptions = youSync.syncRule.playlist.fieldOptions
		} else if (['videos_sync_new', 'videos_update_all', 'videos_update_non_modified', 'videos_update_specific_all', 'videos_update_specific_non_modified'].includes(action)) {
			fieldOptions = youSync.syncRule.video.fieldOptions
		}

		// Get the newly added condition and populate its field select
		const newCondition = conditionsContainer.lastElementChild
		const fieldSelect = newCondition.querySelector('.ys-condition-field')
		if (fieldSelect && fieldOptions) {
			fieldSelect.innerHTML = fieldOptions
		}
	}
})

/**
 * Handle removal of conditions
 */
syncRules.addEventListener('click', function(e) {
	if (e.target.closest('.ys-remove-condition')) {
		e.preventDefault()

		const condition = e.target.closest('.ys-condition')
		const conditionsContainer = condition.closest('.ys-conditions')
		const rule = condition.closest('.ys-sync-rule')

		// Add fade out animation class
		condition.classList.add('ys-pre-remove')
		setTimeout(function() {
			const totalConditions = conditionsContainer.querySelectorAll('.ys-condition').length

			// If this is the last condition, clear the entire container
			if (totalConditions === 1) {
				conditionsContainer.innerHTML = ''
			} else {
				condition.remove()
				// Reindex remaining conditions to ensure sequential numbering
				reindexConditions(rule)
			}
		}, 300)
	}
})

/**
 * Handle adding new sync rules
 */
const addRule = document.getElementById('ys-add-rule')
addRule.addEventListener('click', e => {
  e.preventDefault()

  const rules = [...syncRules.querySelectorAll('.ys-sync-rule')]
  const newIndex = Math.max(-1, ...rules.map(r => +r.dataset.ruleIndex)) + 1
  const template = youSync.syncRule.rule.replaceAll('{{INDEX}}', newIndex)

  syncRules.insertAdjacentHTML('beforeend', template)
})

/**
 * Handle removal of sync rules
 */
syncRules.addEventListener('click', function(e) {
	if (e.target.classList.contains('ys-remove-rule')) {
		const rule = e.target.closest('.ys-sync-rule')
		const totalRules = syncRules.querySelectorAll('.ys-sync-rule').length

		rule.classList.add('ys-pre-remove')
		setTimeout(function() {
			// If this is the last rule, clear the entire container
			if (totalRules === 1) {
				syncRules.innerHTML = ''
			} else {
				rule.remove()
				// Reindex remaining rules to ensure sequential numbering
				reindexRules()
			}
		}, 300)
	}
})