// SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
/* SPDX-License-Identifier: AGPL-3.0-or-later
 * Light public-only banner adapter. No personal cookies, tracking, HTML
 * interpolation, remote asset loading, or authenticated delivery endpoints.
 */
(() => {
  'use strict'
  const appRoot=(window.OC?.webroot||'')+'/index.php/apps/communications'
  const storagePrefix='cc-public-dismissed-'
  const stack=document.createElement('section')
  stack.className='cc-guest-stack'
  stack.setAttribute('aria-label','Avisos públicos')
  stack.setAttribute('role','region')
  let disposed=false
  let pending=false
  let timer=null
  const allowedTypes=new Set(['info','default','primary','success','warning','error','custom','neutral'])
  function safeUrl(value) {
    if (typeof value !== 'string' || !value || value.length > 1900) return ''
    const unsafePunctuation = new Set(['"', "'", '<', '>', '`', '\\'])
    if ([...value].some(char => {
      const code = char.charCodeAt(0)
      return code <= 32 || code === 127 || unsafePunctuation.has(char)
    })) return ''
    if(value.startsWith('/')){
      if(value.startsWith('//'))return ''
      const webroot=window.OC?.webroot||''
      return webroot&&!value.startsWith(webroot+'/')?webroot+value:value
    }
    if(/^https?:\/\//i.test(value)){
      try{
        const parsed=new URL(value)
        return ['http:','https:'].includes(parsed.protocol)&&parsed.hostname&&!parsed.username&&!parsed.password?value:''
      }catch{return ''}
    }
    if(/^mailto:[^@\s]+@[^@\s]+$/i.test(value)||/^tel:\+?[0-9().-]{3,30}$/i.test(value))return value
    return ''
  }
  function dismissed(item) {
    try {return localStorage.getItem(storagePrefix+item.id)===String(item.version)}
    catch{return false}
  }
  function markDismissed(item) {
    try {localStorage.setItem(storagePrefix+item.id,String(item.version))}
    catch{ return }
  }
  function text(tag,content,cls){
    const element=document.createElement(tag)
    if(cls)element.className=cls
    element.textContent=String(content??'')
    return element
  }
  function present(item) {
    const entry=document.createElement('article')
    entry.className='cc-guest-item'
    const type=allowedTypes.has(item.type)?item.type:'info'
    entry.dataset.type=type
    if(type==='custom'&&/^#[0-9a-f]{6}(?:[0-9a-f]{2})?$/i.test(item.color||''))entry.style.setProperty('--cc-guest-accent',item.color)
    const body=document.createElement('div')
    body.className='cc-guest-copy'
    body.append(text('strong',item.headline,'cc-guest-headline'))
    body.append(text('span',item.message,'cc-guest-message'))
    const link=safeUrl(item.ctaUrl)
    if(item.ctaLabel&&link){
      const anchor=text('a',item.ctaLabel,'cc-guest-link')
      anchor.href=link
      if(/^https?:\/\//i.test(link)){
        anchor.target='_blank'
        anchor.rel='noopener noreferrer'
      }
      body.append(anchor)
    }
    entry.append(body)
    if(item.dismissible!==false){
      const close=text('button','×','cc-guest-close')
      close.type='button'
      close.setAttribute('aria-label','Dispensar aviso: '+String(item.headline).slice(0,140))
      close.addEventListener('click',()=>{
        markDismissed(item)
        entry.remove()
        if(!stack.childElementCount)stack.remove()
      })
      entry.append(close)
    }
    return entry
  }
  async function scan(){
    if(disposed||pending||document.visibilityState==='hidden')return
    pending=true
    try{
      const r=await fetch(appRoot+'/api/public-banners',{credentials:'omit',cache:'no-store',headers:{Accept:'application/json'}})
      if(!r.ok)return
      const data=await r.json()
      const items=Array.isArray(data.banners)?data.banners:[]
      stack.replaceChildren()
      for(const item of items.slice(0,5)){
        if(!item||!Number.isSafeInteger(item.id)||dismissed(item))continue
        stack.append(present(item))
      }
      if(stack.childElementCount){
        if(!stack.isConnected)document.body.prepend(stack)
      }else stack.remove()
    }catch{
      // The login and public share pages continue to function if unavailable.
    }finally{pending=false}
  }
  function start(){
    if(!document.body||document.body.id==='body-user')return
    void scan()
    timer=setInterval(()=>void scan(),15000)
    document.addEventListener('visibilitychange',scan)
  }
  if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',start,{once:true})
  else start()
})()
