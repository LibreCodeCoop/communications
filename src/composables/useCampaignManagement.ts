// SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later
import { computed, ref } from 'vue'
import type { Ref } from 'vue'
import { t } from '@nextcloud/l10n'
import { fetchCampaignMetrics } from '../services/campaigns'
import type {
	CampaignCatalog,
	CampaignMetrics,
	CampaignRecord,
} from '../types/communications'
import type { useCampaignStore } from '../stores/campaigns'

type FeedbackLevel = 'info' | 'success' | 'warning' | 'error'
/** The list and confirmation dialogs own management state, not the draft editor. */
export function useCampaignManagement(
	campaignStore: ReturnType<typeof useCampaignStore>,
	catalog: Ref<CampaignCatalog>,
	flash: (text: string, level?: FeedbackLevel) => void,
	errorMessageFor: (error: unknown) => string,
	openEdit: (record: CampaignRecord) => void,
) {
	const confirmArchiveOpen = ref(false)
	const confirmDeleteOpen = ref(false)
	const deleteTarget = ref<CampaignRecord | null>(null)
	const deleteName = ref('')
	const deleting = ref(false)
	const archiveTarget = ref<CampaignRecord | null>(null)

	const archiveConfirmationMessage = computed(() => {
		// TRANSLATORS {name} is the publication being archived; this sentence may be reordered for different languages.
		return t(
			'communications',
			'Archive “{name}”? It will no longer be displayed and can be restored later.',
			{ name: archiveTarget.value?.name || '' },
		)
	})
	const orderSaving = ref(false)

	const editorGroupValues = ref<string[]>([])
	const editorConfigLoading = ref(false)

	const metricsOpen = ref(false)
	const metricsLoading = ref(false)
	const metricsData = ref<CampaignMetrics | null>(null)
	const metricsCampaign = ref<CampaignRecord | null>(null)

	async function saveEditorGroups() {
		if (editorConfigLoading.value || !catalog.value.canConfigureEditors) return
		editorConfigLoading.value = true
		try {
			editorGroupValues.value = await campaignStore.updateEditorGroups(
				editorGroupValues.value,
			)
			// TRANSLATORS Action or instruction in the publications management screen.
			flash(t('communications', 'Editorial groups updated.'), 'success')
		} catch (e) {
			flash(errorMessageFor(e), 'error')
		} finally {
			editorConfigLoading.value = false
		}
	}
	async function updateStatus(c: CampaignRecord, status: string) {
		try {
			await campaignStore.updateStatus(c.id, status)
			flash(
				status === 'active'
					? // TRANSLATORS Short label for an option or field in the publications management screen.
						t('communications', 'Publication activated.')
					: // TRANSLATORS Short label for an option or field in the publications management screen.
						t('communications', 'Status updated.'),
				'success',
			)
		} catch (e) {
			flash(errorMessageFor(e), 'error')
		}
	}
	async function openMetrics(c: CampaignRecord) {
		metricsCampaign.value = c
		metricsData.value = null
		metricsOpen.value = true
		metricsLoading.value = true
		try {
			metricsData.value = await fetchCampaignMetrics(c.id)
		} catch (e) {
			flash(errorMessageFor(e), 'error')
		} finally {
			metricsLoading.value = false
		}
	}
	async function moveCampaign(
		c: CampaignRecord,
		direction: string,
		targetId: number | null = null,
	) {
		if (orderSaving.value || c.status === 'archived') return
		orderSaving.value = true
		try {
			await campaignStore.reorder(c.id, direction, targetId)
			// TRANSLATORS Short label for an option or field in the publications management screen.
			flash(t('communications', 'Display order updated.'), 'success')
		} catch (e) {
			flash(errorMessageFor(e), 'error')
		} finally {
			orderSaving.value = false
		}
	}
	async function duplicate(c: CampaignRecord) {
		try {
			const duplicated = await campaignStore.duplicate(c.id)
			openEdit(duplicated)
			// TRANSLATORS Short label for an option or field in the publications management screen.
			flash(t('communications', 'Copy created as a draft.'), 'success')
		} catch (e) {
			flash(errorMessageFor(e), 'error')
		}
	}
	function askDelete(c: CampaignRecord) {
		if (c.status !== 'archived') return
		deleteTarget.value = c
		deleteName.value = ''
		confirmDeleteOpen.value = true
	}
	async function deletePermanently() {
		if (
			deleting.value
			|| !deleteTarget.value
			|| deleteName.value !== deleteTarget.value.name
		)
			return
		deleting.value = true
		const id = deleteTarget.value.id
		try {
			await campaignStore.purge(id, deleteName.value)
			confirmDeleteOpen.value = false
			deleteTarget.value = null
			deleteName.value = ''
			flash(
				// TRANSLATORS Descriptive message shown in the publications management screen.
				t(
					'communications',
					'Publication and delivery history permanently deleted.',
				),
				'success',
			)
		} catch (e) {
			flash(errorMessageFor(e), 'error')
		} finally {
			deleting.value = false
		}
	}
	function askArchive(c: CampaignRecord) {
		archiveTarget.value = c
		confirmArchiveOpen.value = true
	}
	async function archive() {
		if (!archiveTarget.value) return
		try {
			await campaignStore.archive(archiveTarget.value.id)
			// TRANSLATORS Short label for an option or field in the publications management screen.
			flash(t('communications', 'Publication archived.'), 'success')
		} catch (e) {
			flash(errorMessageFor(e), 'error')
		}
		confirmArchiveOpen.value = false
		archiveTarget.value = null
	}

	return {
		confirmArchiveOpen,
		confirmDeleteOpen,
		deleteTarget,
		deleteName,
		deleting,
		archiveTarget,
		archiveConfirmationMessage,
		orderSaving,
		editorGroupValues,
		editorConfigLoading,
		metricsOpen,
		metricsLoading,
		metricsData,
		metricsCampaign,
		saveEditorGroups,
		updateStatus,
		openMetrics,
		moveCampaign,
		duplicate,
		askDelete,
		deletePermanently,
		askArchive,
		archive,
	}
}
