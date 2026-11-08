import React, { useEffect, useMemo, useState } from 'react';

type Tab = 'brands' | 'fabric';
type Entry = { id: string; name: string; description?: string; active: number; parent_id?: string | null; slug?: string };

export function CategoryManagement() {
  const [tab, setTab] = useState<Tab>('brands');
  const [brands, setBrands] = useState<Entry[]>([]);
  const [fabric, setFabric] = useState<Entry[]>([]);
  const [name, setName] = useState('');
  const [description, setDescription] = useState('');
  const [parentId, setParentId] = useState('');
  const [search, setSearch] = useState('');
  const [message, setMessage] = useState('');
  const [editing, setEditing] = useState<Entry | null>(null);

  const load = async () => {
    const api = window.fxh;
    const [b, f] = await Promise.all([api.brands(), api.fabricTypes()]);
    setBrands(b); setFabric(f);
  };
  useEffect(() => { void load(); }, []);
  const entries = tab === 'brands' ? brands : fabric;
  const filtered = useMemo(() => entries.filter((entry) => entry.name.toLowerCase().includes(search.toLowerCase())), [entries, search]);
  const submit = async (event: React.FormEvent) => {
    event.preventDefault();
    try {
      const type = tab === 'brands' ? 'brand' : 'fabric';
      if (editing) await window.fxh.updateCatalogEntry({ type, id: editing.id, name, description });
      else await window.fxh.createCatalogEntry({ type, name, description });
      setName(''); setDescription(''); setParentId(''); setEditing(null); setMessage(editing ? 'Updated successfully.' : 'Saved successfully.'); await load();
    } catch (error) { setMessage(error instanceof Error ? error.message : 'Could not save entry'); }
  };
  const beginEdit = (entry: Entry) => { setEditing(entry); setName(entry.name); setDescription(entry.description || ''); setParentId(''); setMessage(''); };
  const cancelEdit = () => { setEditing(null); setName(''); setDescription(''); setParentId(''); setMessage(''); };
  const archive = async (entry: Entry) => {
    try { await window.fxh.archiveCatalogEntry({ type: tab === 'brands' ? 'brand' : 'fabric', id: entry.id }); setMessage('Entry deactivated.'); await load(); }
    catch (error) { setMessage(error instanceof Error ? error.message : 'Could not deactivate entry'); }
  };
  return <section>
    <div className="section-toolbar"><div><span className="eyebrow">CATALOGUE CONTROL</span><h2>Brand & Fabric Management</h2></div></div>
    <div className="management-tabs">{[['brands','Brands'],['fabric','Fabric Types']].map(([key,label])=><button key={key} className={tab===key?'active':''} onClick={()=>{setTab(key as Tab);setSearch('')}}>{label}</button>)}</div>
    <div className="content-grid management-grid"><form className="panel" onSubmit={submit}><h3>{editing?'Edit':'Add'} {tab==='brands'?'brand':'fabric type'}</h3><label>Name<input required value={name} onChange={e=>setName(e.target.value)} /></label><label>Description<textarea value={description} onChange={e=>setDescription(e.target.value)}/></label><div className="modal-actions"><button className="primary">{editing?'Save changes':'+ Add new'}</button>{editing&&<button type="button" className="secondary" onClick={cancelEdit}>Cancel</button>}</div>{message&&<div className="success">{message}</div>}</form><div className="panel"><div className="panel-heading"><h3>{tab==='brands'?'Brands':'Fabric Types'}</h3><input className="management-search" placeholder="Search" value={search} onChange={e=>setSearch(e.target.value)}/></div>{filtered.map(entry=><div className="management-row" key={entry.id}><span><strong>{entry.name}</strong></span><div><button className="table-edit" onClick={()=>beginEdit(entry)}>Edit</button><button className="table-delete" onClick={()=>void archive(entry)}>Deactivate</button></div></div>)}{!filtered.length&&<div className="empty">No entries found.</div>}</div></div>
  </section>;
}
