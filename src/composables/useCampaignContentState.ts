// SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later
import { computed, ref } from 'vue'
import type { Ref } from 'vue'
import { t } from '@nextcloud/l10n'
import type {
	CampaignForm,
	TranslatedPublicationField,
} from '../types/communications'

/** Translations and typography remain local to this one publication draft. */
export function useCampaignContentState(form: Ref<CampaignForm>) {
	const languageOptions = [
		// TRANSLATORS Short label for an option or field in the publications management screen.
		{ value: 'pt_BR', label: t('communications', 'Portuguese (Brazil)') },
		// TRANSLATORS Short label for an option or field in the publications management screen.
		{ value: 'en_US', label: t('communications', 'English (United States)') },
		// TRANSLATORS Short label for an option or field in the publications management screen.
		{ value: 'es_ES', label: t('communications', 'Spanish') },
		// TRANSLATORS Short label for an option or field in the publications management screen.
		{ value: 'fr_FR', label: t('communications', 'French') },
		// TRANSLATORS Short label for an option or field in the publications management screen.
		{ value: 'de_DE', label: t('communications', 'German') },
		// TRANSLATORS Short label for an option or field in the publications management screen.
		{ value: 'it_IT', label: t('communications', 'Italian') },
		// TRANSLATORS Short label for an option or field in the publications management screen.
		{ value: 'ar', label: t('communications', 'Arabic (RTL)') },
	]
	const translationLocale = ref('en_US')
	function translatedField(field: TranslatedPublicationField) {
		return computed({
			get: () =>
				form.value.translated_content?.[translationLocale.value]?.[field]
				|| '',
			set: (value) => {
				form.value.translated_content = {
					...(form.value.translated_content || {}),
					[translationLocale.value]: {
						...(form.value.translated_content?.[translationLocale.value]
							|| {}),
						[field]: value || '',
					},
				}
			},
		})
	}
	const translatedHeadline = translatedField('headline')
	const translatedMessage = translatedField('message')
	const translatedCta = translatedField('cta_label')
	const selectedTextColor = computed({
		get: () => form.value.text_color || undefined,
		set: (value) => {
			form.value.text_color = value || ''
		},
	})
	const alignments = [
		// TRANSLATORS Text alignment option for the announcement banner.
		{ value: 'left', label: t('communications', 'Left') },
		// TRANSLATORS Text alignment option for the announcement banner.
		{ value: 'center', label: t('communications', 'Center') },
		// TRANSLATORS Text alignment option for the announcement banner.
		{ value: 'right', label: t('communications', 'Right') },
	]

	return {
		languageOptions,
		translationLocale,
		translatedField,
		translatedHeadline,
		translatedMessage,
		translatedCta,
		selectedTextColor,
		alignments,
	}
}
