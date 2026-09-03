<script setup lang="ts">
import{nextTick,onBeforeUnmount,onMounted,ref,watch}from'vue';
const props=defineProps<{latitude:number|string;longitude:number|string;label?:string}>();
const host=ref<HTMLElement|null>(null);let map:any=null,marker:any=null;
function render(){const L=(window as any).L,lat=Number(props.latitude),lon=Number(props.longitude);if(!host.value||!L||!Number.isFinite(lat)||!Number.isFinite(lon))return;if(!map){map=L.map(host.value,{zoomControl:true,attributionControl:true}).setView([lat,lon],16);L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{maxZoom:19,attribution:'&copy; OpenStreetMap contributors'}).addTo(map);}else map.setView([lat,lon],16);if(marker)marker.remove();marker=L.marker([lat,lon]).addTo(map);if(props.label)marker.bindPopup(props.label);nextTick(()=>map?.invalidateSize());}
onMounted(()=>{render();setTimeout(()=>map?.invalidateSize(),150);});watch(()=>[props.latitude,props.longitude],render);onBeforeUnmount(()=>{map?.remove();map=null;marker=null;});
</script>
<template><div ref="host" class="h-[420px] w-full overflow-hidden rounded-lg border border-[var(--nx-border)] bg-[var(--nx-surface-muted)]" role="img" :aria-label="'Map location for '+(label||'network node')"></div></template>
