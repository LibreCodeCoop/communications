<!-- SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
SPDX-License-Identifier: AGPL-3.0-or-later -->
<script setup lang="ts">
import { t } from '@nextcloud/l10n'
import { useCampaignEditorContext } from '../composables/campaignEditorContext'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcSelectUsers from '@nextcloud/vue/components/NcSelectUsers'
const {
	audienceOptions,
	excludedMatchOptions,
	groupMatchOptions,
	selectable,
	selectedGroups,
	selectedExcludedGroups,
	selectedUsers,
	form,
	groupChoices,
	userChoices,
	searchUsers,
	adminRecipientsLabel,
	adminSendToLabel,
	adminGroupMatchingLabel,
	adminHowToMatchExcludedGroupsLabel,
	adminSelectOneOrMoreGroupsLabel,
	adminRecipientGroupsLabel,
	adminSearchByNameOrIdentifierLabel,
	adminRecipientUsersLabel,
	adminTheseGroupsWillNotReceiveLabel,
	adminExcludedGroupsLabel,
} = useCampaignEditorContext()
</script>
<template>
	<section class="cc-native-section">
		<h2>
			{{ adminRecipientsLabel }}
		</h2>
		<div class="cc-native-control">
			<label for="cc-audience">{{ adminSendToLabel }}</label>
			<NcSelect
				v-model="form.audience_type"
				:options="audienceOptions"
				:reduce="selectable"
				:clearable="false"
				input-id="cc-audience" />
		</div>
		<div v-if="form.audience_type === 'group'" class="cc-native-control">
			<NcSelectUsers
				v-model="selectedGroups"
				:options="groupChoices"
				input-id="cc-group"
				:input-label="adminRecipientGroupsLabel"
				:placeholder="adminSelectOneOrMoreGroupsLabel"
				multiple
				keep-open />
			<div class="cc-native-control">
				<label for="cc-group-match">{{ adminGroupMatchingLabel }}</label>
				<NcSelect
					v-model="form.group_match"
					:options="groupMatchOptions"
					:reduce="selectable"
					:clearable="false"
					input-id="cc-group-match" />
			</div>
		</div>
		<div v-if="form.audience_type === 'users'" class="cc-native-control">
			<NcSelectUsers
				v-model="selectedUsers"
				:options="userChoices"
				input-id="cc-users"
				:input-label="adminRecipientUsersLabel"
				:placeholder="adminSearchByNameOrIdentifierLabel"
				multiple
				keep-open
				@search="searchUsers" />
			<p class="cc-native-hint">
				<!-- TRANSLATORS Action or instruction in the publications management screen. -->
				{{
					t(
						'communications',
						'Enter at least two characters. Only selected users will receive the publication.',
					)
				}}
			</p>
		</div>
		<details
			class="cc-progressive-details"
			:open="selectedExcludedGroups.length > 0 || undefined">
			<summary>
				<!-- TRANSLATORS Descriptive message shown in the publications management screen. -->
				{{ t('communications', 'Exclude recipient groups (optional)') }}
			</summary>
			<div class="cc-native-control">
				<NcSelectUsers
					v-model="selectedExcludedGroups"
					:options="groupChoices"
					input-id="cc-excluded"
					:input-label="adminExcludedGroupsLabel"
					:placeholder="adminTheseGroupsWillNotReceiveLabel"
					multiple
					keep-open />
				<label for="cc-excluded-match">{{
					adminHowToMatchExcludedGroupsLabel
				}}</label>
				<NcSelect
					v-model="form.excluded_match"
					:options="excludedMatchOptions"
					:reduce="selectable"
					:clearable="false"
					input-id="cc-excluded-match" />
				<p class="cc-native-hint">
					<!-- TRANSLATORS Explanatory help text shown in the publications management screen. -->
					{{
						t(
							'communications',
							'Any group (OR) excludes members of at least one group. All groups (AND) excludes only members of every group. Exclusion takes precedence over inclusion.',
						)
					}}
				</p>
			</div>
		</details>
	</section>
</template>
