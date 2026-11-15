<!-- SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
SPDX-License-Identifier: AGPL-3.0-or-later -->
<script setup lang="ts">
import { t } from '@nextcloud/l10n'
import { useCampaignEditorContext } from '../composables/campaignEditorContext'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcColorPicker from '@nextcloud/vue/components/NcColorPicker'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import { mdiPaletteOutline } from '@mdi/js'
const {
	bannerTypes,
	selectable,
	form,
	selectedBannerType,
	bannerIconOptions,
	colorPickerPalette,
	selectedTextColor,
	alignments,
	adminMessageTypeLabel,
	adminBannerColorLabel,
	adminBannerIconLabel,
	adminAllowDismissingTheBannerLabel,
	adminTextAlignmentLabel,
	adminTextColorOptionalLabel,
} = useCampaignEditorContext()
</script>
<template>
	<div v-if="form.presentation === 'banner'" class="cc-banner-type-settings">
		<div class="cc-banner-type-label">
			<span id="cc-banner-type-label" class="cc-native-label">{{
				adminMessageTypeLabel
			}}</span>
			<p class="cc-native-hint">
				<!-- TRANSLATORS Descriptive message shown in the publications management screen. -->
				{{
					t(
						'communications',
						'Use a built-in type or choose a neutral or custom appearance.',
					)
				}}
			</p>
		</div>
		<div
			class="cc-banner-type-options"
			role="group"
			aria-labelledby="cc-banner-type-label">
			<NcButton
				v-for="option in bannerTypes"
				:key="option.value"
				class="cc-banner-type-card"
				:data-type="option.value"
				:pressed="selectedBannerType === option.value"
				:style="
					option.value === 'custom'
						? {
								'--cc-banner-type-background': form.banner_color,
								'--cc-banner-type-accent':
									'color-mix(in srgb, '
									+ form.banner_color
									+ ' 45%, var(--color-main-text) 55%)',
							}
						: undefined
				"
				variant="secondary"
				@click="form.theme = option.value">
				<span class="cc-banner-type-heading">
					<NcIconSvgWrapper :path="option.icon" :size="20" />
					<span>{{ option.label }}</span>
				</span>
			</NcButton>
		</div>
		<div class="cc-banner-custom-fields">
			<div v-if="form.theme === 'custom'" class="cc-banner-color-picker">
				<span class="cc-native-label">{{ adminBannerColorLabel }}</span>
				<NcColorPicker
					v-model="form.banner_color"
					:palette="colorPickerPalette"
					advanced-fields
					container="body">
					<!-- TRANSLATORS Action or instruction in the publications management screen. -->
					<NcButton
						class="cc-banner-color-trigger"
						variant="secondary"
						:aria-label="
							t('communications', 'Choose a custom banner color')
						">
						<span
							class="cc-banner-color-swatch"
							:style="{
								backgroundColor: form.banner_color,
							}"
							aria-hidden="true"></span>
						<span
							>{{ form.banner_color }}
							<!-- TRANSLATORS Short label for an option or field in the publications management screen. -->
							{{ t('communications', '— Choose color') }}</span
						>
					</NcButton>
				</NcColorPicker>
				<!-- TRANSLATORS Descriptive message shown in the publications management screen. -->
				<NcTextField
					v-model="form.banner_color"
					:label="
						t(
							'communications',
							'Hexadecimal code (#RRGGBB or #RRGGBBAA)',
						)
					"
					:maxlength="9" />
				<p class="cc-native-hint">
					<!-- TRANSLATORS Validation or failure message shown in the publications management screen. -->
					{{
						t(
							'communications',
							'The color is softened for readability in light and dark themes. Color does not change meaning: use Warning or Error for genuine alerts.',
						)
					}}
				</p>
			</div>
			<div class="cc-native-control cc-banner-icon-picker">
				<label for="cc-banner-icon">{{ adminBannerIconLabel }}</label>
				<NcSelect
					v-model="form.banner_icon"
					:options="bannerIconOptions"
					:reduce="selectable"
					:clearable="false"
					label="label"
					input-id="cc-banner-icon">
					<template #option="option">
						<span class="cc-banner-icon-item">
							<NcIconSvgWrapper
								v-if="option.path"
								:path="option.path"
								:size="20"
								aria-hidden="true" />
							<span>{{ option.label }}</span>
						</span>
					</template>
					<template #selected-option="option">
						<span class="cc-banner-icon-item">
							<NcIconSvgWrapper
								v-if="option.path"
								:path="option.path"
								:size="18"
								aria-hidden="true" />
							<span>{{ option.label }}</span>
						</span>
					</template>
				</NcSelect>
				<p class="cc-native-hint">
					<!-- TRANSLATORS Descriptive message shown in the publications management screen. -->
					{{
						t(
							'communications',
							'Keep the default icon, choose another, or hide it.',
						)
					}}
				</p>
			</div>
		</div>
	</div>
	<div class="cc-native-appearance-options">
		<NcCheckboxRadioSwitch
			v-if="form.presentation === 'banner'"
			v-model="form.dismissible"
			type="switch"
			>{{ adminAllowDismissingTheBannerLabel }}</NcCheckboxRadioSwitch
		>
	</div>
	<div
		v-if="form.presentation === 'banner' || form.presentation === 'none'"
		class="cc-native-control">
		<label for="cc-text-align">{{ adminTextAlignmentLabel }}</label>
		<NcSelect
			v-model="form.text_align"
			:options="alignments"
			:reduce="selectable"
			:clearable="false"
			input-id="cc-text-align" />
	</div>
	<div
		v-if="form.presentation === 'banner' || form.presentation === 'none'"
		class="cc-banner-color-picker">
		<label>{{ adminTextColorOptionalLabel }}</label>
		<NcColorPicker
			v-model="selectedTextColor"
			clearable
			advanced-fields
			container="body">
			<!-- TRANSLATORS Action or instruction in the publications management screen. -->
			<NcButton
				variant="secondary"
				:text="t('communications', 'Choose text color')">
				<template #icon
					><NcIconSvgWrapper :path="mdiPaletteOutline" :size="18"
				/></template>
			</NcButton>
		</NcColorPicker>
		<p class="cc-native-hint">
			<!-- TRANSLATORS Validation or failure message shown in the publications management screen. -->
			{{
				t(
					'communications',
					'The chosen color is blended with the theme text color to maintain contrast.',
				)
			}}
		</p>
	</div>
</template>
