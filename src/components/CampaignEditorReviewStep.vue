<!-- SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
SPDX-License-Identifier: AGPL-3.0-or-later -->
<script setup lang="ts">
import { t } from '@nextcloud/l10n'
import { useCampaignEditorContext } from '../composables/campaignEditorContext'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcDateTimePickerNative from '@nextcloud/vue/components/NcDateTimePickerNative'
const {
	publishOptions,
	placementOptions,
	targetModeOptions,
	targetAppOptions,
	frequencyOptions,
	categoryOptions,
	themeOptions,
	selectable,
	form,
	hasAdditionalNotices,
	inboxCopy,
	noticeEmail,
	deliveryReview,
	adminVisualDestinationLabel,
	adminNoContextualDisplayLabel,
	adminContextualBannerLabel,
	adminModalDialogLabel,
	adminPriority0100Label,
	adminWhereToDisplayLabel,
	adminFrequencyLimitLabel,
	adminWhereToDisplayThePublicationLabel,
	adminIncludedOrExcludedApplicationsLabel,
	adminCategoryLabel,
	adminVisualThemeLabel,
	adminPublicationStatusLabel,
	adminWhileDraftOrPausedThisHint,
	adminThePublicationWillBecomeActiveHint,
} = useCampaignEditorContext()
</script>
<template>
	<div class="cc-wizard-step-content">
		<div class="cc-wizard-step-title">
			<h2>
				<!-- TRANSLATORS Short label for an option or field in the publications management screen. -->
				{{ t('communications', 'Review and publish') }}
			</h2>
			<p>
				<!-- TRANSLATORS Descriptive message shown in the publications management screen. -->
				{{
					t(
						'communications',
						'Review delivery options before activating the publication.',
					)
				}}
			</p>
		</div>
		<section class="cc-native-section cc-review-delivery">
			<h2>
				<!-- TRANSLATORS Short label for an option or field in the publications management screen. -->
				{{ t('communications', 'Configured delivery') }}
			</h2>
			<p class="cc-review-delivery-line">
				{{ deliveryReview }}
			</p>
			<p v-if="hasAdditionalNotices" class="cc-native-hint">
				<!-- TRANSLATORS Action or instruction in the publications management screen. -->
				{{
					t(
						'communications',
						'Selected channels share the same message and audience. Email and messaging are queued; banners and modals appear during navigation. Links respect permissions even without an Inbox item.',
					)
				}}
			</p>
			<NcNoteCard
				v-if="
					(noticeEmail || form.gateway_channels.length)
					&& form.status === 'active'
				"
				type="warning">
				<!-- TRANSLATORS Explanatory help text shown in the publications management screen. -->
				{{
					t(
						'communications',
						'Activation may send real external messages. Only recipients who opted into each gateway will be included; verify the audience and date range.',
					)
				}}
			</NcNoteCard>
		</section>
		<section class="cc-native-section">
			<h2>
				<!-- TRANSLATORS Short label for an option or field in the publications management screen. -->
				{{ t('communications', 'Publication and frequency') }}
			</h2>
			<div class="cc-native-pair">
				<div class="cc-native-control cc-format-summary">
					<span class="cc-native-label">{{
						adminVisualDestinationLabel
					}}</span>
					<strong>{{
						form.presentation === 'modal'
							? adminModalDialogLabel
							: form.presentation === 'banner'
								? adminContextualBannerLabel
								: adminNoContextualDisplayLabel
					}}</strong>
				</div>
				<div class="cc-native-control cc-native-priority">
					<label for="cc-priority">{{ adminPriority0100Label }}</label>
					<!-- TRANSLATORS Short label for an option or field in the publications management screen. -->
					<NcTextField
						id="cc-priority"
						v-model="form.priority"
						:label="t('communications', 'Priority (0–100)')"
						type="number"
						label-outside />
				</div>
			</div>
			<div class="cc-native-pair">
				<!-- TRANSLATORS Short label for an option or field in the publications management screen. -->
				<NcTextField
					v-model="form.manual_order"
					:label="
						t('communications', 'Manual order (lower numbers first)')
					"
					type="number" />
			</div>
			<div class="cc-native-pair">
				<div
					v-if="
						form.trigger_key !== 'nextcloud.page.opened'
						&& ['banner', 'modal'].includes(form.presentation)
						&& form.app_target_mode === 'area'
					"
					class="cc-native-control">
					<label for="cc-placement">{{ adminWhereToDisplayLabel }}</label>
					<NcSelect
						v-model="form.placement"
						:options="placementOptions"
						:reduce="selectable"
						:clearable="false"
						input-id="cc-placement" />
				</div>
				<div
					v-if="['banner', 'modal'].includes(form.presentation)"
					class="cc-native-control">
					<label for="cc-frequency">{{ adminFrequencyLimitLabel }}</label>
					<NcSelect
						v-model="form.frequency"
						:options="frequencyOptions"
						:reduce="selectable"
						:clearable="false"
						input-id="cc-frequency" />
				</div>
			</div>
			<div
				v-if="
					['banner', 'modal'].includes(form.presentation)
					&& form.trigger_key !== 'nextcloud.page.opened'
				"
				class="cc-native-control cc-app-targeting">
				<label for="cc-app-target-mode">{{
					adminWhereToDisplayThePublicationLabel
				}}</label>
				<NcSelect
					v-model="form.app_target_mode"
					:options="targetModeOptions"
					:reduce="selectable"
					:clearable="false"
					input-id="cc-app-target-mode" />
				<div
					v-if="
						form.app_target_mode === 'only'
						|| form.app_target_mode === 'exclude'
					"
					class="cc-native-control">
					<label for="cc-app-target-apps">{{
						adminIncludedOrExcludedApplicationsLabel
					}}</label>
					<!-- TRANSLATORS Action or instruction in the publications management screen. -->
					<NcSelect
						v-model="form.app_target_apps"
						:options="targetAppOptions"
						:reduce="selectable"
						multiple
						:clearable="true"
						input-id="cc-app-target-apps"
						:placeholder="
							t('communications', 'Select at least one application')
						" />
					<p class="cc-native-hint">
						<!-- TRANSLATORS Explanatory help text shown in the publications management screen. -->
						{{
							t(
								'communications',
								'Rules are enforced server-side. Unknown pages do not receive publications filtered by application.',
							)
						}}
					</p>
				</div>
			</div>
			<div
				v-if="
					form.presentation === 'banner'
					&& form.trigger_key === 'nextcloud.time.window'
				"
				class="cc-native-control cc-guest-target">
				<NcCheckboxRadioSwitch
					v-model="form.guest_visible"
					type="switch"
					:disabled="
						inboxCopy
						|| hasAdditionalNotices
						|| form.audience_type !== 'all'
						|| form.category !== 'institutional'
						|| form.placement !== 'any'
						|| !['area', 'all'].includes(form.app_target_mode)
					">
					<!-- TRANSLATORS Descriptive message shown in the publications management screen. -->
					{{
						t(
							'communications',
							'Also show to signed-out visitors (public pages)',
						)
					}}
				</NcCheckboxRadioSwitch>
				<p class="cc-native-hint">
					<!-- TRANSLATORS Explanatory help text shown in the publications management screen. -->
					{{
						t(
							'communications',
							'Content will be public, including on the login page and public links. This is only for institution-wide notices scheduled by date without application restrictions. It cannot be combined with Inbox or personal notifications. Disabled by default to protect existing publications.',
						)
					}}
				</p>
			</div>
			<div
				v-if="['banner', 'modal'].includes(form.presentation)"
				class="cc-native-pair">
				<!-- TRANSLATORS Short label for an option or field in the publications management screen. -->
				<NcTextField
					v-if="form.frequency === 'custom'"
					v-model="form.min_interval_minutes"
					:label="t('communications', 'Minimum interval in minutes')"
					type="number" />
				<!-- TRANSLATORS Short label for an option or field in the publications management screen. -->
				<NcTextField
					v-model="form.max_total"
					:label="t('communications', 'Maximum per user (0 = unlimited)')"
					type="number" />
				<!-- TRANSLATORS Descriptive message shown in the publications management screen. -->
				<NcTextField
					v-model="form.max_per_day"
					:label="
						t('communications', 'Maximum in 24 hours (0 = unlimited)')
					"
					type="number" />
			</div>
			<div class="cc-native-pair">
				<div class="cc-native-control">
					<label for="cc-category">{{ adminCategoryLabel }}</label>
					<NcSelect
						v-model="form.category"
						:options="categoryOptions"
						:reduce="selectable"
						:clearable="false"
						input-id="cc-category" />
				</div>
				<div v-if="form.presentation === 'modal'" class="cc-native-control">
					<label for="cc-theme">{{ adminVisualThemeLabel }}</label>
					<NcSelect
						v-model="form.theme"
						:options="themeOptions"
						:reduce="selectable"
						:clearable="false"
						input-id="cc-theme" />
				</div>
			</div>
			<p class="cc-native-hint">
				<!-- TRANSLATORS Explanatory help text shown in the publications management screen. -->
				{{
					t(
						'communications',
						'Sponsored publications are clearly marked and can be hidden by users. No external tracking is used.',
					)
				}}
			</p>
			<div
				v-if="form.trigger_key !== 'nextcloud.time.window'"
				class="cc-native-pair">
				<!-- TRANSLATORS Short label for an option or field in the publications management screen. -->
				<NcDateTimePickerNative
					v-model="form.starts_at"
					:label="t('communications', 'Start (optional)')"
					type="datetime-local"
					clearable />
				<!-- TRANSLATORS Optional end date of publication availability. -->
				<NcDateTimePickerNative
					v-model="form.ends_at"
					:label="t('communications', 'End (optional)')"
					type="datetime-local"
					clearable />
			</div>
			<div class="cc-native-control">
				<label for="cc-publish">{{ adminPublicationStatusLabel }}</label>
				<NcSelect
					v-model="form.status"
					:options="publishOptions"
					:reduce="selectable"
					:clearable="false"
					input-id="cc-publish" />
			</div>
		</section>
		<NcNoteCard type="info" class="cc-wizard-review-note">
			{{
				form.status === 'active'
					? adminThePublicationWillBecomeActiveHint
					: adminWhileDraftOrPausedThisHint
			}}
		</NcNoteCard>
	</div>
</template>
