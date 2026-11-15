<!-- SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
SPDX-License-Identifier: AGPL-3.0-or-later -->
<script setup lang="ts">
import { toRef, provide } from 'vue'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import CampaignOverview from './components/CampaignOverview.vue'
import CampaignAdminDialogs from './components/CampaignAdminDialogs.vue'
import CampaignPresentation from './components/CampaignPresentation.vue'
import CampaignPublicationEditor from './components/CampaignPublicationEditor.vue'
import { useCampaignAdminController } from './composables/useCampaignAdminController'
import { campaignEditorContextKey } from './composables/campaignEditorContext'

const props = withDefaults(defineProps<{ createRequest?: number }>(), {
	createRequest: 0,
})
const emit = defineEmits<{ 'editor-view': [view: string] }>()
const controller = useCampaignAdminController(
	toRef(props, 'createRequest'),
	(view) => emit('editor-view', view),
)
provide(campaignEditorContextKey, controller)
const {
	campaigns,
	catalog,
	loading,
	saving,
	view,
	previewOpen,
	feedback,
	feedbackType,
	confirmArchiveOpen,
	confirmDeleteOpen,
	deleteTarget,
	deleteName,
	deleting,
	archiveConfirmationMessage,
	orderSaving,
	previewCampaign,
	editorGroupValues,
	editorConfigLoading,
	metricsOpen,
	metricsLoading,
	metricsData,
	metricsCampaign,
	form,
	triggerOptions,
	groupOptions,
	openPreview,
	closePreview,
	saveEditorGroups,
	updateStatus,
	openMetrics,
	moveCampaign,
	duplicate,
	askDelete,
	deletePermanently,
	askArchive,
	archive,
	openEdit,
	save,
} = controller
</script>

<template>
	<div class="cc-native-app">
		<div class="cc-native-page">
			<NcNoteCard
				v-if="feedback"
				:type="feedbackType"
				class="cc-native-feedback">
				{{ feedback }}
			</NcNoteCard>

			<CampaignOverview
				v-if="view === 'list'"
				:campaigns="campaigns"
				:catalog="catalog"
				:loading="loading"
				:order-saving="orderSaving"
				:editor-config-loading="editorConfigLoading"
				:group-options="groupOptions"
				:trigger-options="triggerOptions"
				v-model:editor-group-values="editorGroupValues"
				@edit="openEdit"
				@preview="openPreview"
				@metrics="openMetrics"
				@move="moveCampaign"
				@status-change="updateStatus"
				@duplicate="duplicate"
				@delete="askDelete"
				@archive="askArchive"
				@save-editor-groups="saveEditorGroups" />

			<CampaignPublicationEditor v-else />
		</div>
	</div>

	<CampaignPresentation
		v-if="
			previewOpen
			&& ['banner', 'modal'].includes((previewCampaign || form).presentation)
		"
		:campaign="previewCampaign || form"
		:open="previewOpen"
		preview
		@close="closePreview" />

	<CampaignAdminDialogs
		:form="form"
		:preview-campaign="previewCampaign"
		:metrics-campaign="metricsCampaign"
		:metrics-loading="metricsLoading"
		:metrics-data="metricsData"
		:delete-target="deleteTarget"
		:deleting="deleting"
		:archive-confirmation-message="archiveConfirmationMessage"
		v-model:preview-open="previewOpen"
		v-model:metrics-open="metricsOpen"
		v-model:confirm-delete-open="confirmDeleteOpen"
		v-model:confirm-archive-open="confirmArchiveOpen"
		v-model:delete-name="deleteName"
		@close-preview="closePreview"
		@delete-permanently="deletePermanently"
		@archive="archive" />
</template>
