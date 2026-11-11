<!-- SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors -->
<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->
<script setup>
import { onMounted, onUnmounted, ref, watch } from 'vue'
const emit=defineEmits(['unread-count'])
import NcButton from '@nextcloud/vue/components/NcButton'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import NcTextArea from '@nextcloud/vue/components/NcTextArea'
import NcRichText from '@nextcloud/vue/components/NcRichText'
import { safeCtaLink } from './linkSafety.js'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import { mdiCommentTextOutline, mdiArrowLeft, mdiMagnify, mdiBellCogOutline } from '@mdi/js'

const BASE = (window.OC?.webroot || '') + '/index.php/apps/communications'
const items = ref([])
const total = ref(0)
const page = ref(1)
const hasMore = ref(false)
const search = ref('')
const filter = ref('all')
const unreadTotal = ref(0)
const loading = ref(true)
const error = ref('')

// Settings belong to the recipient, not to the publication editor. These
// destinations are never obtained from the user's two-factor login settings.
const gatewaySettingsOpen=ref(false)
const gatewaySettingsLoaded=ref(false)
const gatewaySaving=ref(false)
const gatewayLoading=ref(false)
const gatewayError=ref('')
const gatewaySuccess=ref('')
const gatewayChannels=ref([])
const gatewayPlaceholder={
  sms:'+5521999999999', signal:'+5521999999999',
  telegram:'123456789', gowhatsapp:'+5521999999999',
  whatsappbusiness:'+5521999999999', xmpp:'usuario@dominio.com',
}
async function toggleGatewaySettings() {
  gatewaySettingsOpen.value=!gatewaySettingsOpen.value
  if(!gatewaySettingsOpen.value || gatewaySettingsLoaded.value)return
  gatewayLoading.value=true
  gatewayError.value=''
  try {
    const data=await request('/api/gateway-preferences')
    gatewayChannels.value=data.channels||[]
    gatewaySettingsLoaded.value=true
  }catch(e){gatewayError.value=e.message}
  finally{gatewayLoading.value=false}
}
async function saveGatewaySettings() {
  if(gatewaySaving.value)return
  gatewaySaving.value=true
  gatewayError.value=''
  gatewaySuccess.value=''
  try {
    const channels=Object.fromEntries(gatewayChannels.value.map(item=>[
      item.id,{
        enabled:!!item.enabled,
        destination:item.enabled?item.destination.trim():'',
        sponsored:!!item.enabled && !!item.sponsored,
      },
    ]))
    const data=await request('/api/gateway-preferences','POST',{channels})
    gatewayChannels.value=data.channels||[]
    gatewaySuccess.value='Preferências salvas. Você pode cancelar o consentimento a qualquer momento.'
  }catch(e){gatewayError.value=e.message}
  finally{gatewaySaving.value=false}
}

const selected = ref(null)
const comments = ref([])
const commentDraft = ref('')
const mentionOptions = ref([])
const mentionQuery = ref('')
let mentionSearchTimer = null
let mentionGeneration = 0
watch(commentDraft, value=>{
  if(mentionSearchTimer!==null)clearTimeout(mentionSearchTimer)
  const match=String(value).match(/(?:^|\s)@([A-Za-z0-9_.-]{2,40})$/)
  mentionQuery.value=match?.[1]||''
  mentionOptions.value=[]
  const id=selected.value?.id
  const generation=++mentionGeneration
  if(!match || !id)return
  mentionSearchTimer=setTimeout(async()=>{
    try {
      const r=await request('/api/board/'+id+'/mentions?'+new URLSearchParams({search:mentionQuery.value}))
      if(generation!==mentionGeneration || selected.value?.id!==id)return
      mentionOptions.value=(r.users||[]).slice(0,10)
    }catch{
      if(generation===mentionGeneration)mentionOptions.value=[]
    }
  },250)
})
function selectMention(user) {
  if(!user || !/^[A-Za-z0-9_.-]{2,64}$/.test(user.id))return
  commentDraft.value=commentDraft.value.replace(/(?:^|\s)@[A-Za-z0-9_.-]{2,40}$/,
    matched=>matched[0]==='@'?'@'+user.id+' ':matched[0]+'@'+user.id+' ')
  mentionOptions.value=[]
}

const commentBusy = ref(false)
const commentHasMore = ref(false)
const commentBefore = ref(null)
let generation = 0

function dateText(ts) {
  return ts ? new Date(ts * 1000).toLocaleString(navigator.language || 'pt-BR', {
    dateStyle:'medium', timeStyle:'short',
  }) : ''
}
function safeLink(url) { return safeCtaLink(url) }
async function request(path, method = 'GET', body) {
  const opts = {method,credentials:'same-origin',headers:{Accept:'application/json'}}
  if (method!=='GET') {
    opts.headers['Content-Type']='application/json'
    opts.headers.requesttoken = window.OC?.requestToken || window.oc_requesttoken || ''
    opts.body=JSON.stringify(body||{})
  }
  const response=await fetch(BASE+path, opts)
  const data=await response.json().catch(()=>({}))
  if (!response.ok) throw new Error(data.error || 'Falha HTTP '+response.status)
  return data
}
async function refresh() {
  const current=++generation
  loading.value=true
  error.value=''
  try {
    const q=new URLSearchParams({page:String(page.value), search:search.value.trim(),filter:filter.value})
    const data=await request('/api/board?'+q)
    if (current!==generation) return
    items.value=data.announcements || []
    total.value=data.total || 0
    unreadTotal.value=data.unreadTotal||0
    emit('unread-count',unreadTotal.value)
    hasMore.value=!!data.hasMore
  } catch (e) { if (current===generation) error.value=e.message }
  finally { if (current===generation) loading.value=false }
}
function doSearch() { page.value=1; void refresh() }
function switchFilter(value) {filter.value=value;page.value=1;void refresh()}
function changePage(delta) {
  page.value += delta
  void refresh()
  document.querySelector('.cc-board-heading')?.scrollIntoView({block:'start', behavior:'smooth'})
}
async function openItem(id) {
  error.value=''
  try {
    const data=await request('/api/board/'+id)
    selected.value=data.announcement
    if (selected.value.inInbox && !selected.value.readAt) {
      await request('/api/board/'+id+'/read','POST',{})
      selected.value.readAt=Date.now()/1000
      await refresh()
    }
    const url=new URL(location.href)
    url.searchParams.set('id',String(id))
    history.replaceState(null,'',url)
    comments.value=[]
    commentBefore.value=null
    commentHasMore.value=false
    await loadComments()
  } catch (e) { error.value=e.message; selected.value=null }
}
function closeItem() {
  selected.value=null
  comments.value=[]
  commentDraft.value=''
  const url=new URL(location.href)
  url.searchParams.delete('id')
  history.replaceState(null,'',url)
}
async function loadComments(more=false) {
  if (!selected.value) return
  try {
    const id=selected.value.id
    const query=more && commentBefore.value ? '?before='+commentBefore.value : ''
    const data=await request('/api/board/'+id+'/comments'+query)
    if (selected.value?.id!==id) return
    comments.value=more?[...comments.value,...data.comments]:data.comments
    commentHasMore.value=!!data.hasMore
    commentBefore.value=data.nextBefore
  } catch (e) { error.value=e.message }
}
async function sendComment() {
  if (commentBusy.value || !selected.value || !commentDraft.value.trim()) return
  commentBusy.value=true
  error.value=''
  try {
    await request('/api/board/'+selected.value.id+'/comments','POST',{message:commentDraft.value})
    commentDraft.value=''
    await loadComments()
    selected.value.commentCount++
  } catch (e) { error.value=e.message }
  finally { commentBusy.value=false }
}
async function removeComment(comment) {
  if (!selected.value || !comment.canDelete || commentBusy.value) return
  commentBusy.value=true
  try {
    await request('/api/board/'+selected.value.id+'/comments/'+comment.id,'DELETE',{})
    comments.value=comments.value.filter(row=>row.id!==comment.id)
    selected.value.commentCount=Math.max(0,selected.value.commentCount-1)
  } catch (e) { error.value=e.message }
  finally { commentBusy.value=false }
}
function onPopState() {
  const id=Number(new URLSearchParams(location.search).get('id'))
  if (Number.isInteger(id) && id>0 && selected.value?.id!==id) void openItem(id)
  else if (!id) selected.value=null
}
onMounted(async () => {
  await refresh()
  onPopState()
  window.addEventListener('popstate', onPopState)
})
onUnmounted(()=>window.removeEventListener('popstate',onPopState))
</script>

<template>
  <section class="cc-board-app" aria-label="Caixa de entrada">
    <main class="cc-board-main">
      <header class="cc-board-heading">
        <div>
          <h2>Suas mensagens</h2>
          <p>Consulte os comunicados e avisos destinados a você.</p>
        </div>
        <NcButton variant="tertiary" class="cc-gateway-preferences-button"
                  text="Preferências de avisos" :pressed="gatewaySettingsOpen"
                  @click="toggleGatewaySettings">
          <template #icon><NcIconSvgWrapper :path="mdiBellCogOutline" :size="19"/></template>
        </NcButton>
      </header>
      <section v-if="gatewaySettingsOpen" class="cc-gateway-preferences" aria-label="Preferências de mensageria">
        <h3>Receber avisos por outros canais</h3>
        <p>Escolha individualmente onde deseja receber publicações. Os dados usados na
           autenticação em dois fatores não serão reutilizados. Só serão enviadas mensagens
           por canais com seu consentimento explícito e com o provedor configurado.</p>
        <NcNoteCard v-if="gatewayError" type="error" role="alert">{{ gatewayError }}</NcNoteCard>
        <NcNoteCard v-if="gatewaySuccess" type="success" role="status">{{ gatewaySuccess }}</NcNoteCard>
        <p v-if="gatewayLoading">Carregando disponibilidade…</p>
        <div v-else v-for="channel in gatewayChannels" :key="channel.id"
             class="cc-gateway-preference" :data-gateway="channel.id">
          <div class="cc-gateway-preference-heading">
            <NcCheckboxRadioSwitch v-model="channel.enabled" type="switch"
                                   :disabled="!channel.available && !channel.enabled">
              {{ channel.label }}
            </NcCheckboxRadioSwitch>
            <span>{{ channel.available ? 'Configurado' : channel.installed ? 'Provedor não configurado' : 'Aplicativo não instalado' }}</span>
          </div>
          <template v-if="channel.enabled">
            <NcTextField v-model="channel.destination"
                         :label="'Destino para '+channel.label"
                         :placeholder="gatewayPlaceholder[channel.id]" :maxlength="190"
                         :disabled="!channel.available"/>
            <NcCheckboxRadioSwitch v-model="channel.sponsored" type="checkbox"
                                   :disabled="!channel.available">
              Autorizo também conteúdo patrocinado neste canal
            </NcCheckboxRadioSwitch>
          </template>
        </div>
        <p v-if="gatewaySettingsLoaded && !gatewayChannels.some(ch => ch.available)" class="cc-gateway-preferences-hint">
          Nenhum provedor está configurado neste Nextcloud. Solicite ao administrador a instalação
          do Two-Factor Gateway e a configuração de um canal.
        </p>
        <p class="cc-gateway-preferences-hint">
          Desativar um canal e salvar remove seu identificador das preferências.
          O envio é em fila; uma mensagem já enviada não pode ser recolhida.
        </p>
        <NcButton v-if="gatewaySettingsLoaded" text="Salvar preferências"
                  variant="primary" :disabled="gatewaySaving" @click="saveGatewaySettings"/>
      </section>
      <NcNoteCard v-if="error" type="error" role="alert">{{ error }}</NcNoteCard>
      <template v-if="!selected">
        <div class="cc-board-search">
          <NcTextField v-model="search" type="search" label="Pesquisar mensagens"
                       @keyup.enter="doSearch" />
          <NcButton text="Pesquisar" @click="doSearch">
            <template #icon><NcIconSvgWrapper :path="mdiMagnify" :size="20"/></template>
          </NcButton>
        </div>
        <div class="cc-inbox-filters" role="group" aria-label="Filtro de mensagens">
          <NcButton :pressed="filter==='all'" :variant="filter==='all'?'primary':'tertiary'"
                    text="Todas" @click="switchFilter('all')" />
          <NcButton :pressed="filter==='unread'" :variant="filter==='unread'?'primary':'tertiary'"
                    :text="'Não lidas ('+unreadTotal+')'" @click="switchFilter('unread')" />
        </div>
        <p v-if="loading" role="status">Carregando mensagens…</p>
        <NcEmptyContent v-else-if="items.length===0" name="Nenhuma mensagem encontrada"
                        description="Você não tem mensagens nesse filtro." />
        <div v-else class="cc-board-list">
          <article v-for="item in items" :key="item.id" class="cc-board-article" :class="{'cc-board-unread':!item.readAt}"
                   :style="{textAlign:item.textAlign||'left'}">
            <div class="cc-board-metadata">
              <strong v-if="!item.readAt" class="cc-unread-label">Não lida</strong>
              <span>{{ item.author }}</span>
              <time :datetime="new Date(item.publishedAt*1000).toISOString()">{{ dateText(item.publishedAt) }}</time>
              <span v-if="item.endsAt" class="cc-board-chip">Até {{ dateText(item.endsAt) }}</span>
              <span v-if="item.source==='contextual'" class="cc-board-chip">Aviso contextual</span>
              <span v-if="item.category==='sponsored'" class="cc-board-chip">Patrocinado</span>
            </div>
            <h2>{{ item.headline }}</h2>
            <NcRichText :text="item.message" :use-markdown="true" :reference-limit="0" />
            <footer class="cc-board-article-actions">
              <NcButton text="Abrir mensagem" @click="openItem(item.id)">
                <template #icon><NcIconSvgWrapper :path="mdiCommentTextOutline" :size="18"/></template>
              </NcButton>
              <span v-if="item.commentsEnabled">{{ item.commentCount }} comentário(s)</span>
            </footer>
          </article>
        </div>
        <nav v-if="total>10" class="cc-board-pagination" aria-label="Navegação de mensagens">
          <NcButton text="Anterior" :disabled="page<=1" @click="changePage(-1)" />
          <span>Página {{ page }} de {{ Math.ceil(total/10) }}</span>
          <NcButton text="Próxima" :disabled="!hasMore" @click="changePage(1)" />
        </nav>
      </template>
      <template v-else>
        <NcButton text="Voltar à caixa de entrada" variant="tertiary" @click="closeItem">
          <template #icon><NcIconSvgWrapper :path="mdiArrowLeft" :size="18"/></template>
        </NcButton>
        <NcNoteCard v-if="selected.inInbox === false && selected.source === 'canal'" type="info" class="cc-direct-channel-note">
          Mensagem recebida por outro canal. Ela não foi adicionada à sua Caixa de entrada.
        </NcNoteCard>
        <article class="cc-board-article cc-board-detail"
                 :style="{textAlign:selected.textAlign||'left'}">
          <div class="cc-board-metadata">
            <span>{{ selected.author }}</span>
            <time :datetime="new Date(selected.publishedAt*1000).toISOString()">{{ dateText(selected.publishedAt) }}</time>
            <span v-if="selected.endsAt" class="cc-board-chip">Até {{ dateText(selected.endsAt) }}</span>
          </div>
          <h2 dir="auto">{{ selected.headline }}</h2>
          <NcRichText :text="selected.message" :use-markdown="true" :reference-limit="0"/>
          <a v-if="selected.ctaLabel && safeLink(selected.ctaUrl)" class="cc-board-cta"
             :href="safeLink(selected.ctaUrl)" rel="noopener noreferrer">{{ selected.ctaLabel }}</a>
        </article>
        <section v-if="selected.commentsEnabled" class="cc-board-comments" :aria-label="'Comentários de '+selected.headline">
          <h2>Comentários ({{ selected.commentCount }})</h2>
          <form @submit.prevent="sendComment">
            <NcTextArea v-model="commentDraft" label="Escrever comentário"
                        :maxlength="4000" :rows="3" placeholder="Escreva seu comentário. Use @identificador para mencionar uma pessoa." />
            <NcButton variant="primary" text="Publicar comentário" :disabled="commentBusy || !commentDraft.trim()"
                      @click="sendComment" />
          </form>
          <div v-if="comments.length===0" class="cc-board-empty-comments">Ainda não há comentários.</div>
          <div v-for="comment in comments" :key="comment.id" class="cc-board-comment">
            <div class="cc-board-metadata">
              <strong>{{ comment.author }}</strong>
              <time :datetime="new Date(comment.createdAt*1000).toISOString()">{{ dateText(comment.createdAt) }}</time>
              <NcButton v-if="comment.canDelete" size="small" variant="tertiary"
                        text="Excluir" :disabled="commentBusy" @click="removeComment(comment)"/>
            </div>
            <NcRichText :text="comment.message" :use-markdown="true" :reference-limit="0"/>
          </div>
          <NcButton v-if="commentHasMore" text="Carregar comentários anteriores" @click="loadComments(true)"/>
        </section>
      </template>
    </main>
  </section>
</template>
