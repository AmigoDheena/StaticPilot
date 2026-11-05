// Run against the local XAMPP installation: node tests/smoke.cjs
const fs = require('node:fs');
const {execFileSync} = require('node:child_process');
const assert = require('node:assert/strict');
const root = require('node:path').resolve(__dirname, '..');
const base = 'http://localhost/StaticPilot/';
const settings = JSON.parse(execFileSync('C:/xampp/php/php.exe', ['-r', 'echo json_encode(require "setup.php");'], {cwd:root, encoding:'utf8'}));
const dataPath = settings.storage_path + '/content.json';
const existed = fs.existsSync(dataPath), backup = existed ? fs.readFileSync(dataPath) : null;
function php(code) { return execFileSync('C:/xampp/php/php.exe',['-r',code],{cwd:root,encoding:'utf8'}); }
function route(path, prefix='') {
  const setup = prefix ? '$d=content_read(); $d["company"]["base_url"]="https://example.com/client/"; content_save(content_revision(content_read()),function($old)use($d){return $d;});' : '';
  return JSON.parse(php('require "content-store.php";'+setup+' $_SERVER["REQUEST_URI"]='+JSON.stringify(path)+'; require "route.php"; echo json_encode(["status"=>http_response_code() ?: 200,"name"=>$Name,"base"=>$base]);'));
}
let cookie = '';
async function request(path, data) {
  const res = await fetch(base + path, {redirect:'manual', headers:{Cookie:cookie, ...(data ? {'Content-Type':'application/x-www-form-urlencoded'} : {})}, ...(data ? {method:'POST',body:new URLSearchParams(data)} : {})});
  const set = res.headers.get('set-cookie'); if (set) cookie = set.split(';')[0];
  return {status:res.status, body:await res.text(), location:res.headers.get('location')};
}
function hidden(body, name) { const match = body.match(new RegExp('name="'+name+'" value="([^"]*)"')); assert(match, name+' field'); return match[1]; }
async function form(section) { const r = await request('index.php?section='+section); assert.equal(r.status,200); return {csrf:hidden(r.body,'csrf'),revision:hidden(r.body,'revision')}; }
async function save(section, input) { const r = await request('index.php?section='+section,{...await form(section),...input}); assert.equal(r.status,302,r.body.slice(0,500)); }
(async () => {
 try {
  assert.equal((await request('index.php')).status,302);
  let r = await request('login.php');
  r = await request('login.php', {csrf:hidden(r.body,'csrf'),username:settings.username,password:settings.password}); assert.equal(r.status,302);
  await save('company',{action:'save',name:'Test <Company>',email:'test@example.com',phone:'+91 9876543210',whatsapp:'919876543210',address:'Test address',address_short:'Test city',base_url:'https://example.com'});
  let stale = await form('services');
  const row = {name:'Test service',slug:'smoke-test-service',description:'<script>alert(1)</script>',seo_title:'Test SEO',seo_description:'Description'};
  await save('services',{action:'create',...row});
  r = await request('index.php?section=services',{...stale,action:'create',...row,slug:'stale-page'}); assert.match(r.body,/Content changed in another window/);
  r = await request('index.php?section=services',{...await form('services'),action:'create',...row}); assert.match(r.body,/already used/);
  r = await request('index.php?section=services',{...await form('services'),csrf:'bad',action:'delete',id:'0'}); assert.match(r.body,/session expired/);
  await save('services',{action:'create',...row,name:'Second service',slug:'smoke-test-second'});
  await save('services',{action:'down',id:'0'});
  await save('services',{action:'save',id:'0',...row,name:'Updated service'});
  await save('products',{action:'create',...row,slug:'smoke-test-product'});
  await save('pages',{action:'create',...row,slug:'smoke-test-page'});
  await save('menu',{action:'create',name:'Home',link:'/'});
  r = await request('index.php?section=menu',{...await form('menu'),action:'create',name:'Unsafe',link:'javascript:alert(1)'}); assert.match(r.body,/safe website link/);
  await save('company',{action:'save',name:'Test <Company>',phone:'+91 9876543210',base_url:'https://example.com','social[facebook]':'https://facebook.com/example'});
  const custom = {'custom_names[0]':'Rating','custom_values[0]':'<b>Excellent</b>'};
  for (const section of ['company','services','products','pages','menu','testimonials']) {
    const fields = section === 'company' ? {name:'Test <Company>',phone:'+91 9876543210',base_url:'https://example.com'} :
      section === 'menu' ? {name:'Custom link',link:'/'} :
      section === 'testimonials' ? {name:'Asha',position:'Founder',review:'Thoughtful work.'} :
      {...row,slug:'smoke-custom-'+section};
    const singleton = section === 'company';
    await save(section,{action:singleton?'save':'create',...fields,...custom});
    r = await request('index.php?section='+section+(singleton?'':'&edit=0'));
    assert.match(r.body,/value="Rating"/); assert.match(r.body,/&lt;b&gt;Excellent&lt;\/b&gt;/);
    assert.doesNotMatch(r.body,/Warning:|Fatal error:/);
    assert.match(r.body,/aria-label="PHP example for Rating"/);
    assert.match(r.body,/data-copy="code-custom-0"/);
    const snippet = php('require "developer-help.php"; echo developer_section_example('+JSON.stringify(section)+',["Rating"]);');
    assert.match(snippet,/\['Rating'\]/);
    const rendered = php('require "template-data.php"; require "developer-help.php"; $code=developer_section_example('+JSON.stringify(section)+',["Rating"]); $code=str_replace("/StaticPilot/template-data.php", "/template-data.php", $code); eval("?>".$code);');
    assert.match(rendered,/&lt;b&gt;Excellent&lt;\/b&gt;/);
    if (!singleton) {
      const label={services:'Service',products:'Product',pages:'Page',menu:'Navigation Link',testimonials:'Testimonial'}[section];
      const fresh=await request('index.php?section='+section);
      assert.match(fresh.body,new RegExp('Add '+label));
      assert.match(fresh.body,/data-saved-field-names="\[&quot;Rating&quot;\]"/);
    }
    r = await request('index.php?section='+section+(singleton?'':'&edit=0'),{...await form(section),action:'save',id:'0',...fields,...custom,'custom_names[1]':'rating','custom_values[1]':'duplicate'});
    assert.match(r.body,/Custom field names must be unique/); assert.match(r.body,/duplicate/);
    const saved = JSON.parse(php('require "template-data.php"; echo json_encode($website_content['+JSON.stringify(section)+']);'));
    assert.equal((singleton?saved:saved[0]).custom_fields.Rating,'<b>Excellent</b>');
    await save(section,{action:'save',id:'0',...fields});
    const cleared = JSON.parse(php('require "content-store.php"; echo json_encode(content_read()['+JSON.stringify(section)+']);'));
    assert.deepEqual((singleton?cleared:cleared[0]).custom_fields,[]);
    if (!singleton) await save(section,{action:'delete',id:'0'});
  }
  await save('testimonials',{action:'create',name:'Asha',position:'Founder',review:'First review'});
  await save('testimonials',{action:'create',name:'Ravi',position:'Designer',review:'Second review',...custom});
  await save('testimonials',{action:'down',id:'0'});
  await save('testimonials',{action:'save',id:'1',name:'Ravi',position:'Director',review:'Updated review',...custom});
  const testimonials = JSON.parse(php('require "template-data.php"; echo json_encode($testimonials);'));
  assert.equal(testimonials[1].review,'Updated review'); assert.equal(testimonials[1].custom_fields.Rating,'<b>Excellent</b>');
  r = await request('index.php?section=testimonials',{...await form('testimonials'),action:'create',name:'Missing review',review:''}); assert.match(r.body,/Review is required/);
  await save('testimonials',{action:'delete',id:'1'}); await save('testimonials',{action:'delete',id:'0'});
  r = await request('index.php?section=company'); assert.match(r.body,/\$company_name/); assert.match(r.body,/aria-controls="help-name"/); assert.match(r.body,/data-copy="code-name"/);
  for (const section of ['services','products','pages','menu']) { r = await request('index.php?section='+section); assert.match(r.body,/foreach/); }
  r = await request('index.php?section=pages',{...await form('pages'),action:'create',...row,slug:'staticpilot'}); assert.match(r.body,/reserved/);
  assert.equal((await request('storage/content.json')).status,403);
  const vars = JSON.parse(execFileSync('C:/xampp/php/php.exe',['-r','require "template-data.php"; echo json_encode([$company, $phone, $services["smoke-test-service.php"]]);'],{cwd:root,encoding:'utf8'}));
  assert.equal(vars[0],'Test <Company>'); assert.equal(vars[2].Name,'Updated service');
  assert.equal(vars[2].URL,'/smoke-test-service');
  assert.equal(php('require "template-data.php"; echo content_escape($company_name);'),'Test &lt;Company&gt;');
  for (const [path,name] of [['/smoke-test-page','Test service'],['/smoke-test-service','Updated service'],['/smoke-test-product','Test service']]) {
    const result=route(path); assert.equal(result.status,200); assert.equal(result.name,name);
  }
  for (const path of ['/missing','/services/missing','/unknown/nested']) { assert.equal(route(path).status,404); }
  const nested=route('/client/smoke-test-page','/client'); assert.equal(nested.status,200);
  assert.equal(nested.base,'/client/');
  for (const path of ['setup.php','template-data.php','route.php','content-store.php']) { assert.equal((await request(path)).status,403); }
  await save('company',{action:'save',name:'Test <Company>',base_url:'https://example.com'});
  await save('services',{action:'delete',id:'0'});
  assert.equal(route('/smoke-test-service').status,404);
  // Custom section definitions and nested entries share the protected editor workflow.
  await save('custom_sections',{action:'create',name:'Our purpose',slug:'smoke-purpose',description:'What guides us'});
  await save('custom_sections',{action:'create',name:'Why choose us',slug:'smoke-reasons',description:'Our strengths'});
  r = await request('index.php?section=custom_sections');
  assert.match(r.body,/Manage entries/); assert.match(r.body,/permanent key/);
  r = await request('index.php?section=custom_sections',{...await form('custom_sections'),action:'create',name:'Duplicate',slug:'smoke-purpose'});
  assert.match(r.body,/section key is already used/);
  for (const name of ['Mission','Vision','Goals']) await save('custom:smoke-purpose',{action:'create',name,description:name+' description',...custom});
  for (const name of ['Experience','Care','Support']) await save('custom:smoke-reasons',{action:'create',name,description:name+' description'});
  let customStale=await form('custom:smoke-purpose');
  await save('custom:smoke-purpose',{action:'down',id:'0'});
  r = await request('index.php?section=custom:smoke-purpose',{...customStale,action:'save',id:'0',name:'Stale'});
  assert.match(r.body,/Content changed in another window/);
  await save('custom:smoke-purpose',{action:'save',id:'0',name:'Vision updated',description:'<script>test</script>',...custom});
  await save('custom_sections',{action:'save',id:'1',name:'Our values',slug:'smoke-purpose',description:'Updated heading','custom_names[0]':'Subtitle','custom_values[0]':'About us'});
  r = await request('index.php?section=custom_sections',{...await form('custom_sections'),action:'save',id:'1',name:'Changed',slug:'changed-key'});
  assert.match(r.body,/section key cannot be changed/);
  const collections=JSON.parse(php('require "template-data.php"; echo json_encode($custom_sections);'));
  assert.equal(collections['smoke-purpose'].name,'Our values');
  assert.equal(collections['smoke-purpose'].items.length,3);
  assert.equal(collections['smoke-purpose'].items[0].name,'Vision updated');
  assert.equal(collections['smoke-purpose'].items[0].custom_fields.Rating,'<b>Excellent</b>');
  assert.equal(collections['smoke-reasons'].items.length,3);
  for (const section of ['custom:smoke-purpose','custom_sections']) {
    const field=section==='custom_sections'?'Subtitle':'Rating';
    const output=php('require "template-data.php"; require "developer-help.php"; $code=developer_section_example('+JSON.stringify(section)+','+JSON.stringify([field])+'); $code=str_replace("/StaticPilot/template-data.php", "/template-data.php", $code); eval("?>".$code);');
    assert.match(output,/Vision updated/); assert.match(output,/&lt;script&gt;test&lt;\/script&gt;/);
    assert.match(output,section==='custom_sections'?/About us/:/&lt;b&gt;Excellent&lt;\/b&gt;/);
  }
  r = await request('index.php?section=custom:smoke-purpose&edit=0');
  assert.match(r.body,/PHP example for Rating/); assert.match(r.body,/custom:smoke-reasons/);
  assert.doesNotMatch(r.body,/Warning:|Fatal error:/);
  r = await request('index.php?section=custom:smoke-purpose',{...await form('custom:smoke-purpose'),action:'delete',id:'0',csrf:'bad'});
  assert.match(r.body,/session expired/);
  await save('custom:smoke-purpose',{action:'delete',id:'0'});
  await save('custom_sections',{action:'delete',id:'1'});
  assert.equal((await request('index.php?section=custom:smoke-purpose')).status,404);
  const remaining=JSON.parse(php('require "template-data.php"; echo json_encode($custom_sections);'));
  assert.equal(remaining['smoke-purpose'],undefined); assert.equal(remaining['smoke-reasons'].items.length,3);
  await save('custom_sections',{action:'delete',id:'0'});
  r = await request('index.php',{...await form('company'),action:'logout'}); assert.equal(r.status,302);
  assert.equal((await request('index.php')).status,302);
  console.log('PASS: standard editors, custom sections and entries, stable keys, isolation, CRUD/reordering, PHP loops, custom fields, auth, CSRF, conflicts, escaping, routing and storage denial.');
 } finally { if(existed) fs.writeFileSync(dataPath,backup); else if(fs.existsSync(dataPath)) fs.unlinkSync(dataPath); }
})().catch(error=>{console.error(error);process.exitCode=1;});
