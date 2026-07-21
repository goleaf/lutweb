<script setup lang="ts">
export type AppIconName =
    | 'alert-circle'
    | 'arrow-left'
    | 'arrow-right'
    | 'check-circle'
    | 'chevron-right'
    | 'clock'
    | 'close'
    | 'copy'
    | 'credit-card'
    | 'dashboard'
    | 'download'
    | 'edit'
    | 'filter'
    | 'folder'
    | 'globe'
    | 'home'
    | 'image'
    | 'key'
    | 'layers'
    | 'lock'
    | 'login'
    | 'logout'
    | 'mail'
    | 'menu'
    | 'monitor'
    | 'package'
    | 'palette'
    | 'receipt'
    | 'redo'
    | 'refresh'
    | 'register'
    | 'reset'
    | 'save'
    | 'search'
    | 'shield'
    | 'shop'
    | 'sliders'
    | 'sparkles'
    | 'star'
    | 'tag'
    | 'trash'
    | 'undo'
    | 'upload'
    | 'user'
    | 'wand';

withDefaults(
    defineProps<{
        name: AppIconName;
        strokeWidth?: number;
    }>(),
    {
        strokeWidth: 1.8,
    },
);

const icons: Record<AppIconName, string[]> = {
    'alert-circle': [
        'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18Z',
        'M12 7.5v5',
        'M12 16.5h.01',
    ],
    'arrow-left': ['M19 12H5', 'M11 6l-6 6 6 6'],
    'arrow-right': ['M5 12h14', 'M13 6l6 6-6 6'],
    'check-circle': [
        'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18Z',
        'm8 12 2.5 2.5L16.5 8',
    ],
    'chevron-right': ['m9 6 6 6-6 6'],
    clock: ['M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18Z', 'M12 7v5l3 2'],
    close: ['M6 6l12 12', 'M18 6 6 18'],
    copy: [
        'M8 8h10v10H8z',
        'M6 16H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1',
    ],
    'credit-card': [
        'M4 6.5h16a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2v-9a2 2 0 0 1 2-2Z',
        'M2 10h20',
        'M6 15h3',
    ],
    dashboard: [
        'M4 4h7v7H4z',
        'M13 4h7v5h-7z',
        'M13 11h7v9h-7z',
        'M4 13h7v7H4z',
    ],
    download: ['M12 4v11', 'm7 10 5 5 5-5', 'M5 20h14'],
    edit: ['M4 20h4.5L19 9.5a2.1 2.1 0 0 0-3-3L5.5 17H4z', 'M14.5 7.5l2 2'],
    filter: ['M4 6h16', 'M7 12h10', 'M10 18h4'],
    folder: [
        'M3 7.5a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z',
    ],
    globe: [
        'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18Z',
        'M3.5 12h17',
        'M12 3c2.5 2.4 3.8 5.4 3.8 9s-1.3 6.6-3.8 9',
        'M12 3c-2.5 2.4-3.8 5.4-3.8 9s1.3 6.6 3.8 9',
    ],
    home: ['M3 10.5 12 3l9 7.5', 'M5 10v10h14V10', 'M9 20v-6h6v6'],
    image: [
        'M4 5h16a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2Z',
        'm4 16 4.5-4 3 3L15 11l5 5',
        'M8 9h.01',
    ],
    key: [
        'M14 7.5a4.5 4.5 0 1 0-1.3 3.2L21 19v2h-3v-2h-2v-2h-2l-2.4-2.4',
        'M7.5 10h.01',
    ],
    layers: ['M12 3 3 8l9 5 9-5z', 'm3 12 9 5 9-5', 'm3 16 9 5 9-5'],
    lock: ['M6 11h12v9H6z', 'M8.5 11V8.5a3.5 3.5 0 0 1 7 0V11'],
    login: [
        'M10 17l5-5-5-5',
        'M15 12H3',
        'M14 4h5a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-5',
    ],
    logout: [
        'M14 17l5-5-5-5',
        'M19 12H8',
        'M10 20H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h5',
    ],
    mail: [
        'M4 5h16a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2Z',
        'm4 8 8 5 8-5',
    ],
    menu: ['M4 7h16', 'M4 12h16', 'M4 17h16'],
    monitor: ['M4 5h16v11H4z', 'M9 20h6', 'M12 16v4'],
    package: ['M12 3 4 7v10l8 4 8-4V7z', 'm4 7 8 4 8-4', 'M12 11v10'],
    palette: [
        'M12 4a8 8 0 0 0 0 16h1.2a1.8 1.8 0 0 0 1.3-3.1 1.7 1.7 0 0 1 1.2-2.9H17a5 5 0 0 0 0-10z',
        'M7.5 11h.01',
        'M9 7.5h.01',
        'M13 7h.01',
    ],
    receipt: ['M6 3h12v18l-3-2-3 2-3-2-3 2z', 'M9 8h6', 'M9 12h6', 'M9 16h4'],
    redo: ['M17 7h-6a5 5 0 1 0 4 8', 'm13 3 4 4-4 4'],
    refresh: [
        'M20 7v5h-5',
        'M4 17v-5h5',
        'M18 12a6 6 0 0 0-10.2-4.2L4 12',
        'M6 12a6 6 0 0 0 10.2 4.2L20 12',
    ],
    register: [
        'M15 20v-1.5a4.5 4.5 0 0 0-9 0V20',
        'M10.5 11a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7Z',
        'M18 8v6',
        'M15 11h6',
    ],
    reset: [
        'M4 4v6h6',
        'M20 20v-6h-6',
        'M5.5 10A7 7 0 0 1 17 6',
        'M18.5 14A7 7 0 0 1 7 18',
    ],
    save: ['M5 4h12l2 2v14H5z', 'M8 4v6h8V4', 'M8 20v-6h8v6'],
    search: ['M10.5 18a7.5 7.5 0 1 0 0-15 7.5 7.5 0 0 0 0 15Z', 'm16 16 5 5'],
    shield: [
        'M12 3 20 6v6c0 5-3.4 8-8 9-4.6-1-8-4-8-9V6z',
        'm8.5 12 2.5 2.5 4.5-5',
    ],
    shop: ['M5 10h14l-1 10H6z', 'M8 10V8a4 4 0 0 1 8 0v2', 'M4 10 6 4h12l2 6'],
    sliders: [
        'M4 6h9',
        'M17 6h3',
        'M4 12h3',
        'M11 12h9',
        'M4 18h11',
        'M19 18h1',
        'M13 4v4',
        'M9 10v4',
        'M17 16v4',
    ],
    sparkles: [
        'M12 3l1.7 5.1L19 10l-5.3 1.9L12 17l-1.7-5.1L5 10l5.3-1.9z',
        'M5 4v3',
        'M3.5 5.5h3',
        'M19 16v3',
        'M17.5 17.5h3',
    ],
    star: [
        'M12 3l2.7 5.5 6.1.9-4.4 4.3 1 6.1L12 17l-5.4 2.8 1-6.1-4.4-4.3 6.1-.9z',
    ],
    tag: ['M4 4h7l9 9-7 7-9-9z', 'M8 8h.01'],
    trash: [
        'M4 7h16',
        'M9 7V4h6v3',
        'M7 7l1 13h8l1-13',
        'M10 11v5',
        'M14 11v5',
    ],
    undo: ['M7 7h6a5 5 0 1 1-4 8', 'm11 3-4 4 4 4'],
    upload: ['M12 20V9', 'm7 14 5-5 5 5', 'M5 20h14', 'M5 4h14'],
    user: ['M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z', 'M4 21a8 8 0 0 1 16 0'],
    wand: [
        'M4 20 17 7',
        'm14 4 3 3',
        'M6 5v3',
        'M4.5 6.5h3',
        'M18 16v3',
        'M16.5 17.5h3',
    ],
};
</script>

<template>
    <svg
        aria-hidden="true"
        fill="none"
        focusable="false"
        stroke="currentColor"
        :stroke-width="strokeWidth"
        stroke-linecap="round"
        stroke-linejoin="round"
        viewBox="0 0 24 24"
    >
        <path v-for="path in icons[name]" :key="path" :d="path" />
    </svg>
</template>
