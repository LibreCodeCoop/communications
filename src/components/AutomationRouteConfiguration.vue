<!-- SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors -->
<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->
<script setup lang="ts">
import { t } from '@nextcloud/l10n'
import { computed } from 'vue'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import { mdiWeb } from '@mdi/js'
import { updateRouteFilter } from '../services/automationSelection'
import type { PageRouteFilters, PageRouteOption } from '../types/communications'

const props = defineProps<{
	modelValue: string
	pageFilters: PageRouteFilters
	routes: PageRouteOption[]
	loading: boolean
	error: string
}>()
const emit = defineEmits<{
	'update:modelValue': [route: string]
	'update:pageFilters': [filters: PageRouteFilters]
}>()

const ANY_PAGE = '__any_page__'

// TRANSLATORS Label for selecting a web route when configuring a page-visit event.
const routeLabel = t('communications', 'Web page or route *')
// TRANSLATORS Placeholder for the route selector while fetching Nextcloud routes.
const loadingLabel = t('communications', 'Loading pages…')
// TRANSLATORS Accessibility label for selecting a web page as a trigger.
const routeAriaLabel = t('communications', 'Select web page')
// TRANSLATORS Description of a route selection that accepts every page in an app.
const anyPageLabel = t('communications', 'Any page in this app')
// TRANSLATORS Description of matching every recognized interface page in the chosen app.
const allPagesDescription = t('communications', 'All recognized interface pages')
// TRANSLATORS Heading for optional restrictions on URL route variables.
const filtersLabel = t('communications', 'Route variable restrictions')
// TRANSLATORS Heading for the optional URL route variable filter list.
const filtersHeading = t('communications', 'Filters for this route (optional)')
// TRANSLATORS Help text explaining that only route parameters are filtered, not full URLs.
const filtersDescription = t(
	'communications',
	'Restricts only path variable values; no concrete URL is stored. Use safe comparisons instead of arbitrary regular expressions.',
)
// TRANSLATORS Name of a variable appearing in the selected Nextcloud URL pattern.
const variableLabel = t('communications', 'Variable')
// TRANSLATORS Label before a value of an individual URL route variable.
const valueForLabel = t('communications', 'Value for')
// TRANSLATORS Progress message while reading available pages from the Nextcloud router.
const checkingRouterLabel = t(
	'communications',
	'Checking the native Nextcloud router...',
)
// TRANSLATORS Help explaining which web routes are eligible for a page-visit event.
const allowedRoutesDescription = t(
	'communications',
	'Only GET web routes recognized by Nextcloud. API endpoints, OCS routes, files, and private parameters are excluded.',
)

const operatorOptions = [
	// TRANSLATORS Route filter operator accepting any value for the URL variable.
	{ value: 'any', label: t('communications', 'Any value') },
	// TRANSLATORS Route filter operator requiring an exact URL-variable value.
	{ value: 'equals', label: t('communications', 'Equals') },
	// TRANSLATORS Route filter operator matching a URL-variable prefix.
	{ value: 'prefix', label: t('communications', 'Starts with') },
	// TRANSLATORS Route filter operator accepting only digits in a URL variable.
	{ value: 'digits', label: t('communications', 'Numbers only') },
]

const selectedRoute = computed({
	get: () => props.modelValue || ANY_PAGE,
	set: (value: string) => {
		emit('update:modelValue', !value || value === ANY_PAGE ? '' : value)
		emit('update:pageFilters', {})
	},
})
const options = computed(() => [
	{ value: ANY_PAGE, label: anyPageLabel, description: allPagesDescription },
	...props.routes,
])
const variables = computed(() => [
	...new Set(
		[...props.modelValue.matchAll(/\{([A-Za-z][A-Za-z0-9_]*)\}/g)].map(
			(match) => match[1],
		),
	),
])

function changeFilter(
	name: string,
	field: 'operator' | 'value',
	value: string,
): void {
	emit(
		'update:pageFilters',
		updateRouteFilter(props.pageFilters, name, field, value),
	)
}
const chooseRoute = (option: PageRouteOption): string => option.value
const chooseOperator = (option: { value: string }): string => option.value

function filterValueLabel(variable: string): string {
	// TRANSLATORS {variable} is the variable in the Nextcloud URL route being filtered.
	return t('communications', 'Value for {variable}', { variable })
}
function filterExample(variable: string): string {
	// TRANSLATORS {value} is an example of an allowed URL route parameter.
	return t('communications', 'Example: {value}', {
		value: variable === 'view' ? 'recent' : '123',
	})
}
</script>

<template>
	<div class="cc-automation-field">
		<label for="cc-app-route">{{ routeLabel }}</label>
		<NcSelect
			v-model="selectedRoute"
			:options="options"
			:reduce="chooseRoute"
			:clearable="false"
			label="label"
			input-id="cc-app-route"
			:disabled="loading"
			:placeholder="loading ? loadingLabel : ''"
			:aria-label-combobox="routeAriaLabel">
			<template #option="option">
				<div class="cc-automation-option">
					<NcIconSvgWrapper :path="mdiWeb" :size="18" />
					<span>
						<strong>{{ option.label }}</strong>
						<small>{{ option.description }}</small>
					</span>
				</div>
			</template>
		</NcSelect>
		<div
			v-if="modelValue && variables.length"
			class="cc-route-filters"
			role="group"
			:aria-label="filtersLabel">
			<h4>{{ filtersHeading }}</h4>
			<p class="cc-native-hint">{{ filtersDescription }}</p>
			<div
				v-for="variable in variables"
				:key="variable"
				class="cc-route-filter">
				<label :for="'cc-route-operator-' + variable">
					{{ variableLabel }} {{ '{' + variable + '}' }}
				</label>
				<NcSelect
					:input-id="'cc-route-operator-' + variable"
					class="cc-route-filter-select"
					:model-value="pageFilters[variable]?.operator || 'any'"
					:options="operatorOptions"
					:reduce="chooseOperator"
					:clearable="false"
					@update:model-value="
						changeFilter(variable, 'operator', $event || 'any')
					" />
				<template
					v-if="
						['equals', 'prefix'].includes(
							pageFilters[variable]?.operator,
						)
					">
					<label :for="'cc-route-value-' + variable">
						{{ valueForLabel }} {{ variable }}
					</label>
					<NcTextField
						:id="'cc-route-value-' + variable"
						:maxlength="96"
						class="cc-route-filter-value"
						:model-value="pageFilters[variable]?.value || ''"
						:label="filterValueLabel(variable)"
						:placeholder="filterExample(variable)"
						@update:model-value="
							changeFilter(variable, 'value', String($event))
						" />
				</template>
			</div>
		</div>
		<p v-if="loading" class="cc-native-hint">{{ checkingRouterLabel }}</p>
		<NcNoteCard v-if="error" type="error">{{ error }}</NcNoteCard>
		<p class="cc-native-hint">{{ allowedRoutesDescription }}</p>
	</div>
</template>

<style lang="scss" scoped>
.cc-automation-field {
	display: flex;
	flex-direction: column;
	gap: 6px;
	min-width: 0;

	label {
		font-size: 13px;
		font-weight: 650;
	}

	:deep(.v-select) {
		width: 100%;
		max-width: none;
	}
}

.cc-automation-option {
	display: flex;
	align-items: center;
	gap: 9px;
	min-width: 0;

	> span {
		display: flex;
		flex-direction: column;
		gap: 2px;
	}
}

.cc-automation-option strong,
.cc-route-filters h4 {
	font-size: 13px;
	font-weight: 650;
}

.cc-automation-option small,
.cc-native-hint {
	font-size: 12px;
	color: var(--color-text-maxcontrast);
}

.cc-route-filters {
	display: grid;
	gap: 10px;
	padding: 12px;
	border: 1px solid var(--color-border);
	border-radius: 10px;
	background: var(--color-main-background);
}

.cc-route-filter {
	display: grid;
	grid-template-columns: minmax(100px, 1fr) minmax(170px, 1fr);
	gap: 7px 12px;
	align-items: center;

	label {
		font-size: 12px;
	}
}

.cc-route-filter-select,
.cc-route-filter-value {
	min-width: 0;
	width: 100%;
}

.cc-native-hint {
	margin: 2px 0;
}

@media (max-width: 600px) {
	.cc-route-filter {
		grid-template-columns: 1fr;
	}
}
</style>
