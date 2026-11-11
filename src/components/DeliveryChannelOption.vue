<!-- SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors -->
<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->
<script setup>
import { t } from '@nextcloud/l10n'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcPopover from '@nextcloud/vue/components/NcPopover'
import { mdiHelpCircleOutline } from '@mdi/js'

defineProps({
  channel: { type:Object, required:true },
})
const emit = defineEmits(['change'])
function change(channel, enabled) {
  if (channel.selectable) emit('change', {id:channel.id, enabled:!!enabled})
}
</script>

<template>
  <div class="cc-delivery-channel" :data-channel="channel.id"
       :data-availability="channel.availability"
       :data-selected="String(channel.selected)"
       :title="channel.help">
    <div class="cc-delivery-channel-header">
      <div class="cc-delivery-channel-control">
        <NcCheckboxRadioSwitch v-if="channel.selectable"
          :model-value="channel.selected" type="switch"
          @update:modelValue="change(channel, $event)">
          {{ channel.label }}
        </NcCheckboxRadioSwitch>
        <strong v-else class="cc-delivery-channel-unavailable">
          {{ channel.label }}
        </strong>
      </div>
      <div class="cc-delivery-channel-status">
        <span>{{ channel.status }}</span>
        <NcPopover :triggers="['hover', 'focus', 'click']"
                   popup-role="dialog" placement="bottom-end">
          <template #trigger>
            <NcButton variant="tertiary" class="cc-delivery-channel-help"
                      :aria-label="'Detalhes sobre ' + channel.label"
                      :title="t('communications', 'Why is this channel {status}?', {status: channel.status.toLowerCase()})">
              <template #icon>
                <NcIconSvgWrapper :path="mdiHelpCircleOutline" :size="18"/>
              </template>
            </NcButton>
          </template>
          <div class="cc-delivery-channel-help-content">
            <strong>{{ channel.label }}</strong>
            <p>{{ channel.help }}</p>
          </div>
        </NcPopover>
      </div>
    </div>
    <p class="cc-delivery-channel-description">{{ channel.description }}</p>
  </div>
</template>
