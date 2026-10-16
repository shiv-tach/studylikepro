/**
 * The signed-in workspace's shared 24x24 stroke icons, drawn with currentColor
 * so they follow the surrounding text. One icon set for every Vue entry point
 * under `resources/js`, so a card and a chip never disagree on a glyph.
 */
export const icons = {
    sparkles: {
        paths: [
            'M12 3v3.5M12 16v3.5M3 12h3.5M16 12h3.5',
            'M6.4 6.4l2.5 2.5M15.1 15.1l2.5 2.5M17.6 6.4l-2.5 2.5M8.9 15.1l-2.5 2.5',
        ],
        circles: [{ cx: 12, cy: 11, r: 2.4 }],
    },
    camera: {
        paths: ['M4 8.5h3l1.5-2h7l1.5 2h3v10H4z'],
        circles: [{ cx: 12, cy: 13.5, r: 3 }],
    },
    clipboard: {
        paths: [
            'M9 4.5h6v2.5H9z',
            'M8 6H6.5a1 1 0 00-1 1v13a1 1 0 001 1h11a1 1 0 001-1V7a1 1 0 00-1-1H16',
            'M9 12h6M9 15.5h4',
        ],
    },
    calendar: {
        paths: [
            'M7 3.5v3M17 3.5v3',
            'M4.5 8.5h15',
            'M5.5 5.5h13a1 1 0 011 1v13a1 1 0 01-1 1h-13a1 1 0 01-1-1v-13a1 1 0 011-1z',
            'M8.5 12.5h3M8.5 16h3M14.5 12.5h1.5M14.5 16h1.5',
        ],
    },
    user: {
        paths: ['M5 20v-1.2a4.3 4.3 0 014.3-4.3h5.4a4.3 4.3 0 014.3 4.3V20'],
        circles: [{ cx: 12, cy: 7.5, r: 3.5 }],
    },
    users: {
        paths: ['M16 20v-1.5a3.5 3.5 0 00-3.5-3.5h-4A3.5 3.5 0 005 18.5V20', 'M19.5 20v-1.5a3.5 3.5 0 00-2.6-3.4'],
        circles: [{ cx: 10.5, cy: 8, r: 3.5 }],
    },
    book: {
        paths: ['M5 4.5h8.5a3 3 0 013 3v12H8a3 3 0 01-3-3z', 'M16.5 19.5H18a1 1 0 001-1V7'],
    },
    folder: {
        paths: ['M4.5 7.5a2 2 0 012-2h2.9l1.9 2.4h6.2a2 2 0 012 2v7.1a2 2 0 01-2 2h-11a2 2 0 01-2-2z'],
    },
    graduation: {
        paths: [
            'M12 4.5l8.5 3.8-8.5 3.8-8.5-3.8z',
            'M7 10.6V15c0 1.7 2.2 3 5 3s5-1.3 5-3v-4.4',
            'M19.5 9.2V14',
        ],
    },
    palette: {
        paths: ['M12 4a8 8 0 000 16h1.4a1.5 1.5 0 001.1-2.5 1.5 1.5 0 011.1-2.5H17a3 3 0 003-3A8 8 0 0012 4z'],
        circles: [
            { cx: 8.5, cy: 10.5, r: 1 },
            { cx: 12, cy: 8, r: 1 },
            { cx: 15.5, cy: 10.5, r: 1 },
        ],
    },
    'check-circle': {
        paths: ['M8.5 12.4l2.6 2.6 4.9-5.3'],
        circles: [{ cx: 12, cy: 12, r: 8.5 }],
    },
    check: {
        paths: ['M5.5 12.5l4.5 4.5 8.5-9.5'],
    },
    'shield-check': {
        paths: ['M12 3.5l6.5 2.4v5.5c0 3.9-2.7 7.3-6.5 8.6-3.8-1.3-6.5-4.7-6.5-8.6V5.9z', 'M9.2 12.3l2 2 3.7-4.1'],
    },
    star: {
        paths: ['M12 4.5l2.3 4.7 5.2.8-3.8 3.6.9 5.1-4.6-2.5-4.6 2.5.9-5.1-3.8-3.6 5.2-.8z'],
    },
    video: {
        paths: ['M4 7.5h10.5v9H4z', 'M14.5 11l5.5-3.5v9L14.5 13z'],
    },
    clock: {
        paths: ['M12 7.8V12l3 1.8'],
        circles: [{ cx: 12, cy: 12, r: 8 }],
    },
    tag: {
        paths: ['M4.6 11.3V5.7a1.1 1.1 0 011.1-1.1h5.6c.3 0 .6.1.8.3l7.3 7.3a1.1 1.1 0 010 1.6l-5.6 5.6a1.1 1.1 0 01-1.6 0l-7.3-7.3a1.1 1.1 0 01-.3-.8z'],
        circles: [{ cx: 8.4, cy: 8.4, r: 1.2 }],
    },
    search: {
        paths: ['M15.8 15.8L20 20'],
        circles: [{ cx: 11, cy: 11, r: 6.5 }],
    },
    'arrow-right': {
        paths: ['M4.5 12h14', 'M13 6.5l5.5 5.5-5.5 5.5'],
    },
    'chevron-right': {
        paths: ['M9.5 5.5l6.5 6.5-6.5 6.5'],
    },
    'chevron-left': {
        paths: ['M14.5 5.5L8 12l6.5 6.5'],
    },
};

export default icons;
