/**
 * Tiny inline icon set for the landing page: 24x24 stroke paths drawn with
 * currentColor so icons inherit the surrounding text colour. Avoids pulling in
 * an icon package for a handful of marketing sections.
 */
export const icons = {
    'shield-check': {
        paths: ['M12 3l7 3v5.5c0 4.4-2.9 8-7 9.5-4.1-1.5-7-5.1-7-9.5V6z', 'M9 12l2.2 2.2L15.5 10'],
    },
    sparkles: {
        paths: ['M12 3v3.5M12 16v3.5M3 12h3.5M16 12h3.5M6.4 6.4l2.5 2.5M15.1 15.1l2.5 2.5M17.6 6.4l-2.5 2.5M8.9 15.1l-2.5 2.5'],
        circles: [{ cx: 12, cy: 11, r: 2.4 }],
    },
    camera: {
        paths: ['M4 8.5h3l1.5-2h7l1.5 2h3v10H4z'],
        circles: [{ cx: 12, cy: 13.5, r: 3 }],
    },
    users: {
        paths: ['M16 20v-1.5a3.5 3.5 0 00-3.5-3.5h-4A3.5 3.5 0 005 18.5V20', 'M19.5 20v-1.5a3.5 3.5 0 00-2.6-3.4'],
        circles: [{ cx: 10.5, cy: 8, r: 3.5 }],
    },
    calendar: {
        paths: [
            'M7 3.5v3M17 3.5v3',
            'M4.5 8.5h15',
            'M5.5 5.5h13a1 1 0 011 1v13a1 1 0 01-1 1h-13a1 1 0 01-1-1v-13a1 1 0 011-1z',
            'M8.5 12.5h3M8.5 16h3M14.5 12.5h1.5M14.5 16h1.5',
        ],
    },
    card: { paths: ['M3.5 6.5h17v11h-17z', 'M3.5 10.5h17', 'M7 14.5h3'] },
    video: { paths: ['M4 7.5h10.5v9H4z', 'M14.5 11l5.5-3.5v9L14.5 13z'] },
    star: { paths: ['M12 4.5l2.3 4.7 5.2.8-3.8 3.6.9 5.1-4.6-2.5-4.6 2.5.9-5.1-3.8-3.6 5.2-.8z'] },
    clock: { paths: ['M12 7.8V12l3 1.8'], circles: [{ cx: 12, cy: 12, r: 8 }] },
    chat: { paths: ['M4.5 5.5h15v10h-9.5l-5.5 4z', 'M8.5 9.5h7M8.5 12.5h4'] },
    globe: {
        paths: ['M3.5 12h17', 'M12 3.8c2.4 2.5 3.7 5.4 3.7 8.2s-1.3 5.7-3.7 8.2c-2.4-2.5-3.7-5.4-3.7-8.2S9.6 6.3 12 3.8z'],
        circles: [{ cx: 12, cy: 12, r: 8 }],
    },
    search: { paths: ['M20 20l-4.6-4.6'], circles: [{ cx: 11, cy: 11, r: 6 }] },
    filter: {
        paths: ['M4 7h3', 'M13 7h7', 'M4 12h9', 'M19 12h1', 'M4 17h1', 'M11 17h9'],
        circles: [
            { cx: 10, cy: 7, r: 2 },
            { cx: 16, cy: 12, r: 2 },
            { cx: 8, cy: 17, r: 2 },
        ],
    },
    check: { paths: ['M5 12.5l4.5 4.5L19 7'] },
    'chevron-down': { paths: ['M6 9.5l6 6 6-6'] },
    'arrow-up': { paths: ['M12 19V5', 'M6 11l6-6 6 6'] },
    'arrow-right': { paths: ['M4.5 12h14', 'M13 6.5l5.5 5.5-5.5 5.5'] },
    menu: { paths: ['M4 7h16M4 12h16M4 17h16'] },
    close: { paths: ['M6 6l12 12M18 6L6 18'] },
    sun: {
        paths: ['M12 3.5v2M12 18.5v2M3.5 12h2M18.5 12h2M6.4 6.4l1.4 1.4M16.2 16.2l1.4 1.4M17.6 6.4l-1.4 1.4M7.8 16.2l-1.4 1.4'],
        circles: [{ cx: 12, cy: 12, r: 3.4 }],
    },
    moon: { paths: ['M20 14.6A8.6 8.6 0 019.4 4a7.4 7.4 0 1010.6 10.6z'] },
    wallet: { paths: ['M4 7.5h13a1 1 0 011 1v8a1 1 0 01-1 1H4z', 'M17.5 11h4v4h-4a2 2 0 010-4z'] },
    book: { paths: ['M5 4.5h8.5a3 3 0 013 3v12H8a3 3 0 01-3-3z', 'M16.5 19.5H18a1 1 0 001-1V7'] },
    layers: { paths: ['M12 3.5l8 4-8 4-8-4z', 'M4 12l8 4 8-4', 'M4 16.5l8 4 8-4'] },
    clipboard: {
        paths: [
            'M9 4.5h6v2.5H9z',
            'M8 6H6.5a1 1 0 00-1 1v13a1 1 0 001 1h11a1 1 0 001-1V7a1 1 0 00-1-1H16',
            'M9 12h6M9 15.5h4',
        ],
    },
};

export default icons;
