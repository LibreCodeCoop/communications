<!-- SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors -->
<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->
<script setup lang="ts">
import { t } from '@nextcloud/l10n'
import { computed } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcPopover from '@nextcloud/vue/components/NcPopover'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import {
	mdiAccountOutline,
	mdiCommentTextOutline,
	mdiFolderOutline,
	mdiHelpCircleOutline,
	mdiPuzzleOutline,
	mdiShareVariantOutline,
	mdiWeb,
} from '@mdi/js'
import type { NativeApplication } from '../types/communications'

const props = defineProps<{
	modelValue: string | null
	options: NativeApplication[]
	total: number
}>()
const emit = defineEmits<{
	'update:modelValue': [value: string | null]
	search: [term: string]
}>()

const iconPaths: Record<string, string> = {
	files: mdiFolderOutline,
	files_sharing: mdiShareVariantOutline,
	core: mdiAccountOutline,
	comments: mdiCommentTextOutline,
	dashboard: mdiWeb,
}

// TRANSLATORS: Action label for picking which Nextcloud app produces a publication event.
const applicationLabel = t('communications', 'Choose an application *')
// TRANSLATORS: Accessibility label for help explaining the application picker.
const helpAriaLabel = t('communications', 'Help: selecting applications')
// TRANSLATORS: Tooltip title for help about the application picker.
const helpTitle = t('communications', 'Help about applications')
// TRANSLATORS: Help text shown when choosing which Nextcloud applications provide events or pages.
const helpDescription = t(
	'communications',
	'Only enabled apps with integrated events or browsable pages are shown. Pages are discovered automatically. Business events require a trusted adapter.',
)
// TRANSLATORS: Placeholder for searching installed Nextcloud applications.
const searchPlaceholder = t('communications', 'Search installed apps...')
// TRANSLATORS: Accessibility label for the installed-application combobox.
const selectAriaLabel = t('communications', 'Select application')
// TRANSLATORS: Application capability label when both events and pages are available.
const eventsAndPagesLabel = t('communications', 'Events and pages')
// TRANSLATORS: Application capability label when only event triggers are available.
const eventsLabel = t('communications', 'Events available')
// TRANSLATORS: Application capability label when only page-visit triggers are available.
const pagesLabel = t('communications', 'Pages available')
// TRANSLATORS: Hint after the number of installed application options.
const searchHint = t('communications', 'options: search by name to find more.')

const selected = computed({
	get: () => props.modelValue,
	set: (id: string | null) => emit('update:modelValue', id),
})

function capabilityLabel(option: NativeApplication): string {
	if (option.canEvents && option.canPages) return eventsAndPagesLabel
	return option.canEvents ? eventsLabel : pagesLabel
}

function sourceIcon(id: string): string {
	return iconPaths[id] || mdiPuzzleOutline
}

const selectApplication = (application: NativeApplication): string => application.id
</script>

<template>
	<div class="cc-automation-field">
		<div class="cc-field-label">
			<label for="cc-app-source">{{ applicationLabel }}</label>
			<NcPopover
				popup-role="dialog"
				:triggers="['click', 'focus', 'hover']"
				placement="bottom-start"
				class="cc-app-help-popover">
				<template #trigger>
					<NcButton
						variant="tertiary"
						:aria-label="helpAriaLabel"
						:title="helpTitle">
						<template #icon>
							<NcIconSvgWrapper
								:path="mdiHelpCircleOutline"
								:size="20" />
						</template>
					</NcButton>
				</template>
				<p class="cc-help-content">{{ helpDescription }}</p>
			</NcPopover>
		</div>
		<NcSelect
			v-model="selected"
			:options="options"
			:reduce="selectApplication"
			:filterable="false"
			:clearable="true"
			input-id="cc-app-source"
			label="label"
			:placeholder="searchPlaceholder"
			:aria-label-combobox="selectAriaLabel"
			@search="emit('search', $event)">
			<template #option="option">
				<div class="cc-automation-option">
					<img
						v-if="option.icon"
						:src="option.icon"
						alt=""
						class="cc-app-icon" />
					<NcIconSvgWrapper
						v-else
						:path="sourceIcon(option.id)"
						:size="20" />
					<span>
						<strong>{{ option.label }}</strong>
						<small>{{ capabilityLabel(option) }}</small>
					</span>
				</div>
			</template>
			<template #selected-option="option">
				<span class="cc-automation-selected">
					<img
						v-if="option.icon"
						:src="option.icon"
						alt=""
						class="cc-app-icon" />
					<NcIconSvgWrapper
						v-else
						:path="sourceIcon(option.id)"
						:size="18" />
					{{ option.label }}
				</span>
			</template>
		</NcSelect>
		<p v-if="total > 12" class="cc-native-hint">{{ total }} {{ searchHint }}</p>
	</div>
</template>

<style lang="scss" scoped>
.cc-automation-field {
	display: flex;
	flex-direction: column;
	gap: 6px;
	min-width: 0;
}

.cc-automation-field label {
	font-size: 13px;
	font-weight: 650;
}

.cc-field-label {
	display: flex;
	align-items: center;
	gap: 10px;
}

.cc-app-help-popover {
	display: inline-flex;
	align-items: center;
}

.cc-app-help-popover button {
	min-width: 32px;
	min-height: 32px;
}

.cc-help-content {
	max-width: 340px;
	padding: 14px 16px;
	margin: 0;
	font-size: 13px;
	line-height: 1.5;
}

.cc-automation-field :deep(.v-select) {
	width: 100%;
	max-width: none;
}

.cc-automation-option,
.cc-automation-selected {
	display: flex;
	align-items: center;
	gap: 9px;
	min-width: 0;
}

.cc-automation-option > span:last-child {
	display: flex;
	flex-direction: column;
	gap: 2px;
	min-width: 0;
}

.cc-automation-option strong {
	font-size: 13px;
	font-weight: 650;
}

.cc-automation-option small,
.cc-native-hint {
	font-size: 12px;
	color: var(--color-text-maxcontrast);
	white-space: normal;
}

.cc-automation-option img.cc-app-icon,
.cc-automation-selected img.cc-app-icon {
	width: 20px;
	height: 20px;
	flex: none;
	object-fit: contain;
}

.cc-automation-selected img.cc-app-icon {
	width: 18px;
	height: 18px;
}

.cc-native-hint {
	margin: 3px 0 0;
}
</style>
