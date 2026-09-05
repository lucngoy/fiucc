/**
 * Design system FIUCC — FIUCC-20.
 * Couleurs : tokens exacts de CLAUDE.md (aucune couleur de la maquette Stitch
 * n'est reprise ici, son schéma Material 3 ne correspond pas à la charte).
 * Typographie / espacement / rayons / ombres : direction reprise de
 * Projet_Docs_Ref/stitch_document_management_hub/.../DESIGN.md.
 */
module.exports = {
  content: [
    './resources/views/**/*.php',
    './app/**/*.php',
  ],
  theme: {
    extend: {
      colors: {
        primary: {
          DEFAULT: '#0063C6',
          light: '#7BBCFF',
          // Hover du bouton primaire (DESIGN.md, section Buttons) : même teinte
          // assombrie, pas une nouvelle couleur de la charte.
          dark: '#0050A3',
        },
        gold: '#ECC117',
        danger: '#C00F1F',
        surface: '#F8FAFC',
        ink: '#111827',
        muted: '#64748B',
        border: '#E5E7EB',
      },
      fontFamily: {
        sans: ['Inter', 'ui-sans-serif', 'system-ui', '-apple-system', 'Segoe UI', 'Roboto', 'sans-serif'],
      },
      fontSize: {
        'display-hero': ['48px', { lineHeight: '56px', letterSpacing: '-0.025em', fontWeight: '800' }],
        'display-hero-mobile': ['32px', { lineHeight: '40px', letterSpacing: '-0.02em', fontWeight: '800' }],
        'headline-lg': ['36px', { lineHeight: '44px', letterSpacing: '-0.02em', fontWeight: '700' }],
        'headline-lg-mobile': ['26px', { lineHeight: '34px', letterSpacing: '-0.015em', fontWeight: '700' }],
        'headline-md': ['24px', { lineHeight: '32px', letterSpacing: '-0.015em', fontWeight: '700' }],
        'headline-sm': ['18px', { lineHeight: '26px', letterSpacing: '-0.01em', fontWeight: '600' }],
        'body-lg': ['18px', { lineHeight: '28px', fontWeight: '400' }],
        'body-md': ['16px', { lineHeight: '24px', fontWeight: '400' }],
        'body-sm': ['14px', { lineHeight: '20px', fontWeight: '400' }],
        'label-lg': ['14px', { lineHeight: '20px', letterSpacing: '0.01em', fontWeight: '600' }],
        'label-md': ['12px', { lineHeight: '16px', letterSpacing: '0.02em', fontWeight: '600' }],
        'label-caps': ['11px', { lineHeight: '16px', letterSpacing: '0.06em', fontWeight: '700' }],
      },
      maxWidth: {
        layout: '1200px',
      },
      borderRadius: {
        // Noms explicites plutôt que réutiliser sm/md/lg : la section "Shapes"
        // de DESIGN.md (boutons/inputs = 8px, cartes = 12px) contredit son
        // propre bloc de tokens bruts (rounded.md = 12px) — on lève l'ambiguïté.
        control: '8px',
        card: '12px',
        sm: '4px',
      },
      boxShadow: {
        // Niveaux d'élévation de DESIGN.md — jamais d'ombre lourde.
        card: '0 1px 3px rgba(17, 24, 39, 0.05), 0 1px 2px rgba(17, 24, 39, 0.03)',
        hover: '0 4px 6px -1px rgba(17, 24, 39, 0.07), 0 2px 4px -1px rgba(17, 24, 39, 0.04)',
        nav: '0 10px 15px -3px rgba(17, 24, 39, 0.08), 0 4px 6px -2px rgba(17, 24, 39, 0.03)',
      },
    },
  },
  plugins: [],
};
