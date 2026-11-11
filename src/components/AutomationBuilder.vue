<!-- SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors -->
<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->
<script setup>
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
  {value:ANY_PAGE,label:'Qualquer página deste aplicativo',description:'Todas as páginas de interface reconhecidas'},
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
  props.pageRoute==='' ? 'Qualquer página do aplicativo'
    : routes.value.find(route=>route.value===props.pageRoute)?.label||props.pageRoute
)
const webhookExample = computed(()=>JSON.stringify({
  campaignId:props.campaignId||'<ID da campanha após salvar>',
  uid:'usuario_nextcloud', eventId:'evento-unico-001',
},null,2))
const summary = computed(()=>{
  if(selectedIntent.value==='board')return 'Disponível assim que ativar'
  if(selectedIntent.value==='schedule')return 'Durante o período definido, na próxima visita elegível'
  if(selectedIntent.value==='advanced'&&selectedAdvanced.value)
    return selectedAdvanced.value.label+' → '+selectedAdvanced.value.actions[0].label
  if(!selectedApp.value)return 'Escolha um aplicativo'
  if(selectedMode.value==='visit')return selectedApp.value.label+' → '+selectedRouteLabel.value
  return selectedAction.value?selectedApp.value.label+' → '+selectedAction.value.label:'Selecione um evento do aplicativo'
})
const explanation = computed(()=>{
  if(selectedIntent.value==='board')
    return 'Ao ativar, os canais de envio entram na fila; o conteúdo aparece na Caixa de entrada se selecionada e banners ou janelas serão vistos na próxima visita elegível.'
  if(selectedIntent.value==='schedule')
    return 'A publicação só se torna elegível durante a vigência; a apresentação ocorre quando o usuário voltar ao Nextcloud.'
  if(selectedIntent.value==='advanced'&&selectedAdvancedId.value==='webhook')
    return 'O evento HMAC indica campaignId e uid. Apenas esta campanha e o usuário indicado podem receber a apresentação, respeitando o público-alvo e a frequência.'
  if(selectedIntent.value==='advanced'&&selectedAdvancedId.value==='flow')
    return 'O Flow verifica as condições configuradas antes de registrar a ocorrência para o usuário.'
  if(selectedMode.value==='visit'&&selectedApp.value)
    return props.pageRoute ? 'Apenas quando o usuário autenticado entrar nessa rota de interface. Parâmetros variáveis da URL não são armazenados.'
      : 'Ao abrir qualquer página de interface de '+selectedApp.value.label+'. Chamadas de API, downloads e páginas de outros aplicativos não disparam esta campanha.'
  return selectedAction.value?.description||'Escolha um evento confiável emitido pelo aplicativo.'
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
    if(generation===routeGeneration)routeError.value='Não foi possível consultar as páginas deste aplicativo.'
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
    <h2 class="cc-automation-title">Quando disponibilizar a publicação?</h2>

    <div class="cc-automation-intents" role="group" aria-label="Origem do disparo">
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
          <div class="cc-field-label"><label for="cc-app-source">Escolha um aplicativo *</label>
            <NcPopover popup-role="dialog" :triggers="['click', 'focus', 'hover']" placement="bottom-start" class="cc-app-help-popover">
              <template #trigger>
                <NcButton variant="tertiary" aria-label="Ajuda: seleção de aplicativos" title="Ajuda sobre aplicativos">
                  <template #icon><NcIconSvgWrapper :path="mdiHelpCircleOutline" :size="20" /></template>
                </NcButton>
              </template>
              <p class="cc-help-content">Somente aplicativos habilitados com eventos integrados ou páginas navegáveis aparecem. A descoberta das páginas é automática. Um aplicativo só oferece eventos de negócio quando possui um adaptador confiável.</p>
            </NcPopover>
          </div>
          <NcSelect v-model="appValue" :options="appOptions" :reduce="app=>app.id"
                    :filterable="false" :clearable="true" input-id="cc-app-source"
                    label="label" placeholder="Pesquisar aplicativos instalados..."
                    aria-label-combobox="Selecionar aplicativo"
                    @search="appSearch=$event">
            <template #option="option">
              <div class="cc-automation-option">
                <img v-if="option.icon" :src="option.icon" alt="" class="cc-app-icon" />
                <NcIconSvgWrapper v-else :path="sourceIcon(option.id)" :size="20"/>
                <span>
                  <strong>{{ option.label }}</strong>
                  <small>
                    {{ option.canEvents&&option.canPages?'Eventos e páginas':
                      option.canEvents?'Eventos disponíveis':'Páginas disponíveis' }}
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
          <p v-if="applications.length > 12" class="cc-native-hint">{{ applications.length }} opções: pesquise pelo nome para encontrar outras.</p>
        </div>

        <template v-if="selectedApp">
          <div v-if="canSelectEvent&&canSelectPage" class="cc-automation-mode">
            <NcRadioGroup v-model="modeValue" label="O que deve acontecer?">
              <div class="cc-automation-mode-options">
                <NcRadioGroupButton value="event" label="Um evento do aplicativo" />
                <NcRadioGroupButton value="visit" label="Acesso a uma página" />
              </div>
            </NcRadioGroup>
          </div>
          <div v-else class="cc-native-hint cc-automation-only-mode">
            {{ canSelectPage?'Este aplicativo oferece páginas de interface.':'Este aplicativo oferece eventos integrados, mas não possui página própria na navegação.' }}
          </div>

          <div v-if="selectedMode==='event'" class="cc-automation-field">
            <label for="cc-app-event">Evento do aplicativo *</label>
            <NcSelect v-model="actionValue" :options="actionOptions"
                      :reduce="action=>action.value" :clearable="false"
                      input-id="cc-app-event" label="label"
                      placeholder="Selecione o evento..."
                      aria-label-combobox="Selecionar evento do aplicativo">
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
            <label for="cc-app-route">Página ou rota web *</label>
            <NcSelect v-model="pageRouteValue" :options="routeOptions"
                      :reduce="route=>route.value" :clearable="false" label="label"
                      input-id="cc-app-route" :disabled="routeLoading"
                      :placeholder="routeLoading ? 'Consultando páginas...' : ''"
                      aria-label-combobox="Selecionar página web">
              <template #option="option">
                <div class="cc-automation-option">
                  <NcIconSvgWrapper :path="mdiWeb" :size="18"/>
                  <span><strong>{{ option.label }}</strong><small>{{ option.description }}</small></span>
                </div>
              </template>
            </NcSelect>
            <div v-if="pageRoute && routeVariables.length" class="cc-route-filters" role="group" aria-label="Restrições das variáveis da rota">
              <h4>Filtros para esta rota (opcionais)</h4>
              <p class="cc-native-hint">Restringe somente os valores das variáveis do caminho; nenhuma URL concreta é armazenada. Use comparações seguras no lugar de expressões regulares arbitrárias.</p>
              <div v-for="variable in routeVariables" :key="variable" class="cc-route-filter">
                <label :for="'cc-route-operator-'+variable">Variável {{ '{'+variable+'}' }}</label>
                <select :id="'cc-route-operator-'+variable" class="cc-route-filter-select"
                        :value="pageFilters[variable]?.operator||'any'"
                        @change="changeRouteFilter(variable,'operator',$event.target.value)">
                  <option value="any">Qualquer valor</option>
                  <option value="equals">Igual a</option>
                  <option value="prefix">Começa com</option>
                  <option value="digits">Somente números</option>
                </select>
                <template v-if="['equals','prefix'].includes(pageFilters[variable]?.operator)">
                  <label :for="'cc-route-value-'+variable">Valor para {{ variable }}</label>
                  <input :id="'cc-route-value-'+variable" type="text" maxlength="96"
                         class="cc-route-filter-value" :value="pageFilters[variable]?.value||''"
                         :placeholder="'Ex.: '+(variable==='view'?'recent':'123')"
                         @input="changeRouteFilter(variable,'value',$event.target.value)" />
                </template>
              </div>
            </div>
            <p v-if="routeLoading" class="cc-native-hint">Consultando o roteador nativo do Nextcloud...</p>
            <NcNoteCard v-if="routeError" type="error">{{ routeError }}</NcNoteCard>
            <p class="cc-native-hint">Somente rotas web GET reconhecidas pelo Nextcloud. Endpoints API, OCS, arquivos e parâmetros privados são excluídos.</p>
          </div>
        </template>
      </div>

      <div v-else-if="selectedIntent==='board'" class="cc-automation-configuration">
        <NcNoteCard type="info">Sem gatilho técnico: a publicação fica disponível na Caixa de entrada ao ser ativada. Os destinatários e os avisos são configurados na próxima etapa.</NcNoteCard>
      </div>

      <div v-else-if="selectedIntent==='schedule'" class="cc-automation-configuration">
        <p class="cc-native-hint">A mensagem ficará disponível no período escolhido e aparecerá na próxima visita do usuário.</p>
      </div>

      <div v-else class="cc-automation-configuration">
        <h3>Integração ou Flow</h3>
        <div class="cc-automation-field">
          <label for="cc-advanced">Fonte do evento *</label>
          <NcSelect v-model="advancedValue" :options="advancedSources" :reduce="source=>source.id"
                    :clearable="true" input-id="cc-advanced" label="label" placeholder="Escolher integração..."/>
        </div>
        <NcNoteCard v-if="selectedAdvanced" type="info">
          <p>{{ selectedAdvanced.setup }}</p>
          <div v-if="selectedAdvanced.id==='webhook'" class="cc-automation-webhook">
            <p v-if="!campaignId">Salve primeiro a campanha para obter seu identificador e configurar a integração.</p>
            <p v-else>Use o identificador {{ campaignId }} no campo <code>campaignId</code>. A integração não envia a campanha para um grupo inteiro.</p>
            <pre>{{ webhookExample }}</pre>
          </div>
          <div v-if="selectedAdvanced.id==='flow'" class="cc-automation-flow">
            <p v-if="campaignId">Selecione esta campanha pelo nome na operação “Disparar campanha contextual” do Flow.</p>
            <p v-else>Salve a campanha antes de criar o vínculo com a regra do Flow.</p>
            <NcButton text="Abrir Nextcloud Flow" :href="flowLink()" target="_blank">
              <template #icon><NcIconSvgWrapper :path="mdiOpenInNew" :size="18"/></template>
            </NcButton>
          </div>
        </NcNoteCard>
        <NcNoteCard v-else type="info">O Flow avalia condições internas. Um webhook HMAC registra um evento somente para o usuário indicado em <code>uid</code>; não faz divulgação em massa.</NcNoteCard>
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
