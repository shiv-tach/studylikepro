/**
 * The dashboard's own 24x24 stroke icons, drawn with currentColor so they follow
 * the surrounding text. Kept local to this entry point: the signed-in dashboard
 * never pulls in the landing page bundle, and vice versa.
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
    'arrow-right': {
        paths: ['M4.5 12h14', 'M13 6.5l5.5 5.5-5.5 5.5'],
    },
};

export default icons;
