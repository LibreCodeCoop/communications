<!-- SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors -->
<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->
<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'

const props = defineProps({ modelValue:{type:String,default:''} })
const emit = defineEmits(['update:model-value'])
const campaigns = ref([])
const loading = ref(true)
const loadError = ref('')
const selected = computed({
  get:()=>String(props.modelValue || ''),
  set:value=>emit('update:model-value',String(value || '')),
})
const options = computed(()=>campaigns.value.map(c=>({
  value:String(c.id),
  label:c.name + (c.status==='active'?'':' — '+c.status),
})))
const reduce = option=>option.value
onMounted(async()=>{
  try {
    const response=await fetch((window.OC?.webroot||'')+'/index.php/apps/communications/api/campaigns',{credentials:'same-origin'})
    if(!response.ok)throw new Error('HTTP '+response.status)
    const data=await response.json()
    campaigns.value=(data.campaigns||[]).filter(c=>c.trigger_key==='nextcloud.flow.matched' && c.status!=='archived')
  } catch(err) {loadError.value='Não foi possível carregar as campanhas: '+err.message}
  finally {loading.value=false}
})
</script>

<template>
  <div class="cc-flow-picker" data-testid="flow-campaign-picker">
    <label class="cc-flow-picker-label" for="cc-flow-picker-select">Campanha a apresentar</label>
    <p class="cc-flow-picker-hint">Selecione uma campanha configurada com o gatilho “Regra do Flow”.</p>
    <p v-if="loading">Carregando campanhas…</p>
    <NcNoteCard v-else-if="loadError" type="error">{{ loadError }}</NcNoteCard>
    <NcNoteCard v-else-if="!options.length" type="info">
      Nenhuma campanha de Flow encontrada. Crie uma em Campanhas e selecione “Regra avançada ou integração”.
    </NcNoteCard>
    <NcSelect v-else v-model="selected" :reduce="reduce" :options="options"
              input-id="cc-flow-picker-select" :clearable="false"
              placeholder="Escolher uma campanha" />
  </div>
</template>
