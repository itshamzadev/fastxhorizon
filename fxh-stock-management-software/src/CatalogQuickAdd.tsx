import React, { useState } from 'react';

export function CatalogQuickAdd({ type, onCreated }: { type: 'brand' | 'fabric'; onCreated: (name: string) => void }) {
  const [open, setOpen] = useState(false);
  const [name, setName] = useState('');
  const save = async () => {
    const value = name.trim();
    if (!value) return;
    await window.fxh.createCatalogEntry({ type, name: value });
    onCreated(value);
    setName('');
    setOpen(false);
  };
  return <span className="catalog-quick-add"><button type="button" className="quiet" onClick={() => setOpen(!open)}>+ Add New</button>{open && <span className="quick-add-popover"><input autoFocus value={name} onChange={e => setName(e.target.value)} placeholder={type === 'brand' ? 'Brand name' : 'Fabric type'} /><button type="button" className="secondary" onClick={() => void save()}>Save</button></span>}</span>;
}
