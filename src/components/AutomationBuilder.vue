<!-- SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors -->
<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->
<script setup>
import { t } from '@nextcloud/l10n'

import { computed, ref, watch } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcPopover from '@nextcloud/vue/components/NcPopover'
import NcRadioGroup from '@nextcloud/vue/components/NcRadioGroup'
import NcRadioGroupButton from '@nextcloud/vue/components/NcRadioGroupButton'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import {
  mdiApps, mdiCalendarMonthOutline, mdiSourceBranch, mdiFolderOutline,
  mdiShareVariantOutline, mdiAccountOutline, mdiCommentTextOutline,
  mdiPuzzleOutline, mdiCheckCircleOutline, mdiOpenInNew, mdiWeb, mdiHelpCircleOutline, mdiBullhornOutline,
} from '@mdi/js'

const props = defineProps({
  modelValue: { type:String, default:'' },
  placement: { type:String, default:'any' },
  pageRoute: { type:String, default:'' },
  pageFilters: { type:Object, default:()=>({}) },
  catalog: { type:Object, default:()=>({intents:[],sources:[],applications:[],placements:[]}) },
  campaignId: { type:Number, default:null },
})
const emit = defineEmits(['update:modelValue','update:placement','update:pageRoute','update:pageFilters','intent-change'])
const BASE = (window.OC?.webroot || '') + '/index.php/apps/communications'
const selectedIntent = ref(null)
const selectedAppId = ref(null)
const selectedMode = ref('event')
const selectedAdvancedId = ref(null)
const appSearch = ref('')
const MAX_OPTIONS = 12
const routes = ref([])
const routeLoading = ref(false)
const routeError = ref('')
let routeGeneration = 0

const intentIcons = {app:mdiApps, schedule:mdiCalendarMonthOutline, advanced:mdiSourceBranch, board:mdiBullhornOutline}
const sourceIcons = {
  files:mdiFolderOutline, files_sharing:mdiShareVariantOutline, core:mdiAccountOutline,
  comments:mdiCommentTextOutline, dashboard:mdiWeb,
}
const sources = computed(()=>props.catalog.sources || [])
const applications = computed(()=>props.catalog.applications || [])
const intents = computed(()=>props.catalog.intents || [])
const selectedApp = computed(()=>applications.value.find(app=>app.id===selectedAppId.value)||null)
const advancedSources = computed(()=>sources.value.filter(source=>source.intent==='advanced'))
const selectedAdvanced = computed(()=>advancedSources.value.find(source=>source.id===selectedAdvancedId.value)||null)
const selectedAction = computed(()=>selectedApp.value?.actions?.find(action=>action.key===props.modelValue)||null)
const actionOptions = computed(()=>(selectedApp.value?.actions||[]).map(action=>({
  label:action.label,value:action.key,description:action.description,
})))
const filteredApplications = computed(()=>{
  const term = appSearch.value.trim().toLocaleLowerCase('pt-BR')
  if(!term)return applications.value
  return applications.value.filter(app=>[
    app.label,app.description,...(app.keywords||[]),
    ...(app.actions||[]).flatMap(action=>[action.label,action.description]),
  ].join(' ').toLocaleLowerCase('pt-BR').includes(term))
})
const appOptions = computed(()=>{
  const chosen=selectedApp.value
  const first=filteredApplications.value.slice(0,MAX_OPTIONS)
  return chosen&&!first.some(app=>app.id===chosen.id)
    ? [chosen,...first.slice(0,MAX_OPTIONS-1)] : first
})
const appValue = computed({
  get:()=>selectedAppId.value,
  set:id=>selectApp(id),
})
const actionValue = computed({
  get:()=>props.modelValue,
  set:key=>emit('update:modelValue',key||''),
})
const advancedValue = computed({
  get:()=>selectedAdvancedId.value,
  set:id=>{
    selectedAdvancedId.value=id
    const source=advancedSources.value.find(item=>item.id===id)
    emit('update:modelValue',source?.actions[0]?.key||'')
    emit('update:pageRoute','')
  },
})
const modeValue = computed({
  get:()=>selectedMode.value,
  set:mode=>selectMode(mode),
})
// Do not use an empty value for NcSelect: it is interpreted as "no selection"
// and the placeholder overlaps the selected label on some Nextcloud themes.
const ANY_PAGE = '__any_page__'
const pageRouteValue = computed({
  get:()=>props.pageRoute || ANY_PAGE,
  set:route=>{
    emit('update:pageRoute',!route || route===ANY_PAGE ? '' : route)
    emit('update:pageFilters',{})
  },
})
const routeOptions = computed(()=>[
  {value:ANY_PAGE,label:t('communications', 'Any page in this app'),description:t('communications', 'All recognized interface pages')},
  ...routes.value,
])
const routeVariables = computed(()=>[...new Set([...props.pageRoute.matchAll(/\{([A-Za-z][A-Za-z0-9_]*)\}/g)].map(match=>match[1]))])
function changeRouteFilter(name, field, value) {
  const next={...props.pageFilters}
  if (field==='operator' && value==='any') {
    delete next[name]
  } else {
    const rule={...(next[name]||{operator:'equals',value:''}),[field]:value}
    if (field==='operator' && value==='digits') rule.value=''
    next[name]=rule
  }
  emit('update:pageFilters',next)
}
watch([()=>props.modelValue,()=>props.placement], (next,previous)=>{
  if (previous[0] !== next[0] || previous[1] !== next[1]) emit('update:pageFilters',{})
})
const selectedRouteLabel = computed(()=>
  props.pageRoute==='' ? t('communications', 'Any application page')
    : routes.value.find(route=>route.value===props.pageRoute)?.label||props.pageRoute
)
const webhookExample = computed(()=>JSON.stringify({
  campaignId:props.campaignId||t('communications', '<campaign ID after saving>'),
  uid:'nextcloud_user', eventId:'unique-event-001',
},null,2))
const summary = computed(()=>{
  if(selectedIntent.value==='board')return t('communications', 'Available on activation')
  if(selectedIntent.value==='schedule')return t('communications', 'During the chosen period, on the next eligible visit')
  if(selectedIntent.value==='advanced'&&selectedAdvanced.value)
    return selectedAdvanced.value.label+' → '+selectedAdvanced.value.actions[0].label
  if(!selectedApp.value)return t('communications', 'Choose an app')
  if(selectedMode.value==='visit')return selectedApp.value.label+' → '+selectedRouteLabel.value
  return selectedAction.value?selectedApp.value.label+' → '+selectedAction.value.label:t('communications', 'Select an application event')
})
const explanation = computed(()=>{
  if(selectedIntent.value==='board')
    return t('communications', 'On activation, outbound channels are queued; content appears in Inbox if selected, and banners or dialogs appear on the next eligible visit.')
  if(selectedIntent.value==='schedule')
    return t('communications', 'The publication becomes eligible only within its availability period; it appears when the user returns to Nextcloud.')
  if(selectedIntent.value==='advanced'&&selectedAdvancedId.value==='webhook')
    return t('communications', 'The HMAC event specifies campaignId and uid. Only this campaign and the specified user can receive the presentation, subject to audience and frequency limits.')
  if(selectedIntent.value==='advanced'&&selectedAdvancedId.value==='flow')
    return t('communications', 'Flow checks the configured conditions before recording the occurrence for that user.')
  if(selectedMode.value==='visit'&&selectedApp.value)
    return props.pageRoute ? t('communications', 'Only when the authenticated user opens this interface route. Variable URL parameters are not stored.')
      : t('communications', 'When opening any interface page in ')+selectedApp.value.label+t('communications', '. API calls, downloads, and other apps\' pages do not trigger this campaign.')
  return selectedAction.value?.description||t('communications', 'Choose a trusted event emitted by the application.')
})
const canSelectEvent = computed(()=>!!selectedApp.value?.canEvents)
const canSelectPage = computed(()=>!!selectedApp.value?.canPages)

function syncSelection(){
  if(!props.modelValue)return
  if(props.modelValue==='nextcloud.board.published'){
    selectedIntent.value='board'
    return
  }
  if(props.modelValue==='nextcloud.time.window'){
    selectedIntent.value='schedule'
    return
  }
  const advanced=advancedSources.value.find(source=>source.actions?.some(action=>action.key===props.modelValue))
  if(advanced){
    selectedIntent.value='advanced'
    selectedAdvancedId.value=advanced.id
    return
  }
  selectedIntent.value='app'
  if(props.modelValue==='nextcloud.page.opened'){
    selectedMode.value='visit'
    if(props.placement!=='any')selectedAppId.value=props.placement
    return
  }
  const app=applications.value.find(app=>app.actions?.some(action=>action.key===props.modelValue))
  if(app){selectedMode.value='event';selectedAppId.value=app.id}
}
watch([()=>props.modelValue,()=>props.placement,applications,advancedSources],syncSelection,{immediate:true})

function chooseIntent(id){
  if(selectedIntent.value===id)return
  emit('intent-change', {from:selectedIntent.value, to:id})
  selectedIntent.value=id
  appSearch.value=''
  selectedAppId.value=null
  selectedAdvancedId.value=null
  if(id==='board'){
    emit('update:modelValue','nextcloud.board.published')
    emit('update:placement','any')
    emit('update:pageRoute','')
  }else if(id==='schedule'){
    emit('update:modelValue','nextcloud.time.window')
    emit('update:placement','any')
    emit('update:pageRoute','')
  }else{
    emit('update:modelValue','')
    emit('update:placement','any')
    emit('update:pageRoute','')
  }
}
function selectApp(id){
  selectedAppId.value=id||null
  appSearch.value=''
  const app=applications.value.find(x=>x.id===id)
  if(!app){emit('update:modelValue','');emit('update:placement','any');emit('update:pageRoute','');return}
  const mode=app.canEvents?'event':'visit'
  selectedMode.value=mode
  selectMode(mode)
}
function selectMode(mode){
  if(!selectedApp.value)return
  if(mode==='event'&&!canSelectEvent.value)return
  if(mode==='visit'&&!canSelectPage.value)return
  selectedMode.value=mode
  emit('update:pageRoute','')
  if(mode==='visit'){
    emit('update:placement',selectedApp.value.id)
    emit('update:modelValue','nextcloud.page.opened')
  }else{
    emit('update:placement','any')
    const current=selectedApp.value.actions.find(action=>action.key===props.modelValue)
    emit('update:modelValue',current?.key||(
      selectedApp.value.actions.length===1 ? selectedApp.value.actions[0].key : ''
    ))
  }
}
async function loadRoutes(app){
  const generation=++routeGeneration
  routes.value=[]
  routeError.value=''
  if(!app){return}
  routeLoading.value=true
  try{
    const response=await fetch(BASE+'/api/routes?app='+encodeURIComponent(app),{credentials:'same-origin'})
    if(!response.ok)throw new Error('HTTP '+response.status)
    const json=await response.json()
    if(generation===routeGeneration)routes.value=json.routes||[]
  }catch(error){
    if(generation===routeGeneration)routeError.value=t('communications', 'Could not retrieve pages for this app.')
  }finally{
    if(generation===routeGeneration)routeLoading.value=false
  }
}
watch([selectedAppId,selectedMode],([app,mode])=>{
  if(mode==='visit'&&app)void loadRoutes(app)
  else{routeGeneration++;routes.value=[]}
},{immediate:true})
function sourceIcon(id){return sourceIcons[id]||mdiPuzzleOutline}
function flowLink(){return (window.OC?.webroot||'')+'/index.php/settings/admin/workflow'}
</script>

<template>
  <div class="cc-automation" data-testid="automation-builder">
    <h2 class="cc-automation-title">{{ t('communications', 'When should the publication become available?') }}</h2>

    <div class="cc-automation-intents" role="group" :aria-label="t('communications', 'Trigger source')">
      <NcButton v-for="intent in intents" :key="intent.id"
                class="cc-automation-intent-card"
                :pressed="selectedIntent===intent.id"
                :variant="selectedIntent===intent.id?'primary':'secondary'"
                :aria-label="intent.label"
                @click="chooseIntent(intent.id)">
        <span class="cc-automation-intent-title">
          <NcIconSvgWrapper :path="intentIcons[intent.id]||mdiApps" :size="20"/>
          <strong>{{ intent.label }}</strong>
        </span>
        <span class="cc-automation-intent-description">{{ intent.description }}</span>
      </NcButton>
    </div>

    <div v-if="selectedIntent" class="cc-automation-setup">
      <div v-if="selectedIntent==='app'" class="cc-automation-configuration">

        <div class="cc-automation-field">
          <div class="cc-field-label"><label for="cc-app-source">{{ t('communications', 'Choose an application *') }}</label>
            <NcPopover popup-role="dialog" :triggers="['click', 'focus', 'hover']" placement="bottom-start" class="cc-app-help-popover">
              <template #trigger>
                <NcButton variant="tertiary" :aria-label="t('communications', 'Help: selecting applications')" :title="t('communications', 'Help about applications')">
                  <template #icon><NcIconSvgWrapper :path="mdiHelpCircleOutline" :size="20" /></template>
                </NcButton>
              </template>
              <p class="cc-help-content">{{ t('communications', 'Only enabled apps with integrated events or browsable pages are shown. Pages are discovered automatically. Business events require a trusted adapter.') }}</p>
            </NcPopover>
          </div>
          <NcSelect v-model="appValue" :options="appOptions" :reduce="app=>app.id"
                    :filterable="false" :clearable="true" input-id="cc-app-source"
                    label="label" :placeholder="t('communications', 'Search installed apps...')"
                    :aria-label-combobox="t('communications', 'Select application')"
                    @search="appSearch=$event">
            <template #option="option">
              <div class="cc-automation-option">
                <img v-if="option.icon" :src="option.icon" alt="" class="cc-app-icon" />
                <NcIconSvgWrapper v-else :path="sourceIcon(option.id)" :size="20"/>
                <span>
                  <strong>{{ option.label }}</strong>
                  <small>
                    {{ option.canEvents&&option.canPages?t('communications', 'Events and pages'):
                      option.canEvents?t('communications', 'Events available'):t('communications', 'Pages available') }}
                  </small>
                </span>
              </div>
            </template>
            <template #selected-option="option">
              <span class="cc-automation-selected">
                <img v-if="option.icon" :src="option.icon" alt="" class="cc-app-icon" />
                <NcIconSvgWrapper v-else :path="sourceIcon(option.id)" :size="18"/> {{ option.label }}
              </span>
            </template>
          </NcSelect>
          <p v-if="applications.length > 12" class="cc-native-hint">{{ applications.length }} {{ t('communications', 'options: search by name to find more.') }}</p>
        </div>

        <template v-if="selectedApp">
          <div v-if="canSelectEvent&&canSelectPage" class="cc-automation-mode">
            <NcRadioGroup v-model="modeValue" :label="t('communications', 'What should happen?')">
              <div class="cc-automation-mode-options">
                <NcRadioGroupButton value="event" :label="t('communications', 'An application event')" />
                <NcRadioGroupButton value="visit" :label="t('communications', 'Page visit')" />
              </div>
            </NcRadioGroup>
          </div>
          <div v-else class="cc-native-hint cc-automation-only-mode">
            {{ canSelectPage?t('communications', 'This app provides interface pages.'):t('communications', 'This app provides integrated events but has no navigation page.') }}
          </div>

          <div v-if="selectedMode==='event'" class="cc-automation-field">
            <label for="cc-app-event">{{ t('communications', 'Application event *') }}</label>
            <NcSelect v-model="actionValue" :options="actionOptions"
                      :reduce="action=>action.value" :clearable="false"
                      input-id="cc-app-event" label="label"
                      :placeholder="t('communications', 'Select an event...')"
                      :aria-label-combobox="t('communications', 'Select application event')">
              <template #option="option">
                <div class="cc-automation-option">
                  <NcIconSvgWrapper :path="mdiCheckCircleOutline" :size="18"/>
                  <span><strong>{{ option.label }}</strong><small>{{ option.description }}</small></span>
                </div>
              </template>
            </NcSelect>
            <p v-if="selectedAction" class="cc-native-hint">{{ selectedAction.description }}</p>
          </div>

          <div v-else class="cc-automation-field">
            <label for="cc-app-route">{{ t('communications', 'Web page or route *') }}</label>
            <NcSelect v-model="pageRouteValue" :options="routeOptions"
                      :reduce="route=>route.value" :clearable="false" label="label"
                      input-id="cc-app-route" :disabled="routeLoading"
                      :placeholder="routeLoading ? t('communications', 'Loading pages…') : ''"
                      :aria-label-combobox="t('communications', 'Select web page')">
              <template #option="option">
                <div class="cc-automation-option">
                  <NcIconSvgWrapper :path="mdiWeb" :size="18"/>
                  <span><strong>{{ option.label }}</strong><small>{{ option.description }}</small></span>
                </div>
              </template>
            </NcSelect>
            <div v-if="pageRoute && routeVariables.length" class="cc-route-filters" role="group" :aria-label="t('communications', 'Route variable restrictions')">
              <h4>{{ t('communications', 'Filters for this route (optional)') }}</h4>
              <p class="cc-native-hint">{{ t('communications', 'Restricts only path variable values; no concrete URL is stored. Use safe comparisons instead of arbitrary regular expressions.') }}</p>
              <div v-for="variable in routeVariables" :key="variable" class="cc-route-filter">
                <label :for="'cc-route-operator-'+variable">{{ t('communications', 'Variable') }} {{ '{'+variable+'}' }}</label>
                <select :id="'cc-route-operator-'+variable" class="cc-route-filter-select"
                        :value="pageFilters[variable]?.operator||'any'"
                        @change="changeRouteFilter(variable,'operator',$event.target.value)">
                  <option value="any">{{ t('communications', 'Any value') }}</option>
                  <option value="equals">{{ t('communications', 'Equals') }}</option>
                  <option value="prefix">{{ t('communications', 'Starts with') }}</option>
                  <option value="digits">{{ t('communications', 'Numbers only') }}</option>
                </select>
                <template v-if="['equals','prefix'].includes(pageFilters[variable]?.operator)">
                  <label :for="'cc-route-value-'+variable">{{ t('communications', 'Value for') }} {{ variable }}</label>
                  <input :id="'cc-route-value-'+variable" type="text" maxlength="96"
                         class="cc-route-filter-value" :value="pageFilters[variable]?.value||''"
                         :placeholder="'Ex.: '+(variable==='view'?'recent':'123')"
                         @input="changeRouteFilter(variable,'value',$event.target.value)" />
                </template>
              </div>
            </div>
            <p v-if="routeLoading" class="cc-native-hint">{{ t('communications', 'Checking the native Nextcloud router...') }}</p>
            <NcNoteCard v-if="routeError" type="error">{{ routeError }}</NcNoteCard>
            <p class="cc-native-hint">{{ t('communications', 'Only GET web routes recognized by Nextcloud. API endpoints, OCS routes, files, and private parameters are excluded.') }}</p>
          </div>
        </template>
      </div>

      <div v-else-if="selectedIntent==='board'" class="cc-automation-configuration">
        <NcNoteCard type="info">{{ t('communications', 'Without a technical trigger, the publication becomes available in Inbox when activated. Recipients and notifications are configured in the next step.') }}</NcNoteCard>
      </div>

      <div v-else-if="selectedIntent==='schedule'" class="cc-automation-configuration">
        <p class="cc-native-hint">{{ t('communications', 'The message will be available during the selected period and appear on the user\'s next visit.') }}</p>
      </div>

      <div v-else class="cc-automation-configuration">
        <h3>{{ t('communications', 'Integration or Flow') }}</h3>
        <div class="cc-automation-field">
          <label for="cc-advanced">{{ t('communications', 'Event source *') }}</label>
          <NcSelect v-model="advancedValue" :options="advancedSources" :reduce="source=>source.id"
                    :clearable="true" input-id="cc-advanced" label="label" :placeholder="t('communications', 'Choose an integration...')"/>
        </div>
        <NcNoteCard v-if="selectedAdvanced" type="info">
          <p>{{ selectedAdvanced.setup }}</p>
          <div v-if="selectedAdvanced.id==='webhook'" class="cc-automation-webhook">
            <p v-if="!campaignId">{{ t('communications', 'Save the campaign first to get its identifier and configure the integration.') }}</p>
            <p v-else>{{ t('communications', 'Use the identifier') }} {{ campaignId }} {{ t('communications', 'in the field') }} <code>campaignId</code>{{ t('communications', '. The integration does not send the campaign to an entire group.') }}</p>
            <pre>{{ webhookExample }}</pre>
          </div>
          <div v-if="selectedAdvanced.id==='flow'" class="cc-automation-flow">
            <p v-if="campaignId">{{ t('communications', 'Select this campaign by name in Flow\'s “Trigger contextual campaign” operation.') }}</p>
            <p v-else>{{ t('communications', 'Save the campaign before linking it to the Flow rule.') }}</p>
            <NcButton :text="t('communications', 'Open Nextcloud Flow')" :href="flowLink()" target="_blank">
              <template #icon><NcIconSvgWrapper :path="mdiOpenInNew" :size="18"/></template>
            </NcButton>
          </div>
        </NcNoteCard>
        <NcNoteCard v-else type="info">{{ t('communications', 'Flow evaluates internal conditions. An HMAC webhook records an event only for the user specified in') }} <code>uid</code>{{ t('communications', '; it never broadcasts to all users.') }}</NcNoteCard>
      </div>

      <div v-if="modelValue && selectedIntent && selectedIntent !== 'schedule'" class="cc-automation-result" role="status">
        <div class="cc-automation-result-label">
          <NcIconSvgWrapper :path="modelValue?mdiCheckCircleOutline:mdiPuzzleOutline" :size="18"/>
          <strong>{{ summary }}</strong>
        </div>
        <p>{{ explanation }}</p>
      </div>
    </div>
  </div>
</template>
