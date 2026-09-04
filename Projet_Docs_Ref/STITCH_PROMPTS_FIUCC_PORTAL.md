# Google Stitch — Prompt pack
## FIUCC Portal — Congolese Community of Final International University

**Comment l'utiliser**

1. Colle d'abord le **PROMPT 0** (design system). Ne génère rien d'autre dans ce message : il sert à fixer le style pour toute la session.
2. Ensuite, un prompt = un écran. Colle-les un par un, dans l'ordre, dans le **même projet Stitch** pour que le contexte reste cohérent.
3. Si un écran part dans la mauvaise direction, itère par petites touches ("make the hero shorter", "use the gold accent only on the CTA") plutôt que de tout réécrire.
4. Les prompts sont en anglais volontairement : Stitch produit de meilleurs résultats et les libellés d'interface du site sont en anglais de toute façon.
5. Tout est en **web / desktop-first avec comportement responsive décrit**. Pour une version mobile d'un écran, ajoute à la fin : `Now generate the mobile version of this screen (390px width).`

---

## PROMPT 0 — Design system (à envoyer en premier)

```
You are designing a multi-page web platform. Before generating any screen, adopt this design system and keep it consistent across every screen in this project.

PRODUCT CONTEXT
FIUCC Portal — the official digital portal of the Congolese Community of Final International University (Congolese students studying abroad). It is not a brochure site: it is the permanent digital hub of the community. Students discover the community, register (census), browse events, revisit photo memories, meet other members, vote in community elections, become alumni, and come back as mentors.

USERS
- New Congolese students arriving at the university (18-22), mobile-first, unfamiliar with the community.
- Registered members checking events, photos and the member directory.
- Alumni and mentors keeping in touch after graduation.
- 3-5 community admins managing members, events and elections.

TONE
Modern, institutional, trustworthy, subtly African, university-flavored. Think "official student government portal", not "2010 association website", not "startup landing page", not "NGO template".

COLOR TOKENS (use these exact hex values)
- Primary / brand blue: #0063C6 — navigation, primary buttons, section titles, links
- Light blue: #7BBCFF — soft backgrounds, hover states, cards, secondary elements
- Gold accent: #ECC117 — badges, highlights, secondary CTAs, active states
- Red: #C00F1F — alerts, destructive actions, cultural accents, urgent labels
- Page background: #F8FAFC
- Text primary: #111827
- Text muted: #64748B
- Border / divider: #E5E7EB
- Card surface: #FFFFFF

COLOR RULES (important)
- Do not use the four brand colors at equal intensity. Blue dominates, light blue supports, gold and red are accents only (max ~5% of the surface each).
- Never place gold text on white. Gold is for fills, badges, underlines and small icons.
- Red is reserved for alerts, errors and destructive actions. Never use red for regular buttons.
- Large surfaces are white or #F8FAFC. No dark theme, no gradients across whole sections. A subtle blue gradient is allowed on the hero only.

TYPOGRAPHY
- Sans-serif throughout (Inter or similar). Headings semi-bold to bold, tight tracking. Body 16px, generous line height. Clear size jumps between h1/h2/h3 — hierarchy must be readable at a glance.

LAYOUT & COMPONENTS
- Max content width 1200px, centered, generous whitespace, 8px spacing scale.
- Cards: white, 12px radius, 1px #E5E7EB border, very soft shadow. No heavy drop shadows.
- Buttons: 8px radius. Primary = solid #0063C6 white text. Secondary = white with #0063C6 border and text. Accent = solid #ECC117 with #111827 text. Destructive = solid #C00F1F.
- Badges/pills for statuses: Student (blue), Alumni (gold), Mentor (light blue), Admin (red).
- Inputs: white, 1px #E5E7EB border, focus ring in #0063C6, labels above the field.
- Every screen has the same sticky top navigation and the same footer unless stated otherwise.

GLOBAL NAVIGATION (identical on every public screen)
Left: circular community logo + wordmark "Congolese Community of Final".
Center links: Home · Community · Government · Events · Gallery · News · Members · Election.
Right: language switcher (EN / FR), "Sign in" text link, and a solid blue "Join the community" button.
Sticky, white background, 1px bottom border, subtle shadow on scroll. On mobile it collapses into a hamburger drawer.

FOOTER (identical everywhere)
Four columns: About the community (short paragraph + logo) · Quick links · Community (directory, alumni, mentors, documents) · Contact (email, WhatsApp group, Instagram). Bottom bar in #0063C6 with copyright and "Made by the Congolese Community of Final".

ACCESSIBILITY
WCAG AA contrast on all text. Visible focus states. Never rely on color alone to convey meaning — always pair with an icon or a label.

Confirm you have registered this design system, then wait for my next message describing the first screen. Do not generate a screen yet.
```

---

## PROMPT 1 — Home page

```
Screen: Home page (web, desktop 1440px).

Goal: a first-time visitor must understand in 5 seconds what this community is, feel it is alive, and be pushed to register.

Layout, top to bottom:
1. Hero, full width, ~70vh. Background: photo of Congolese students together on campus, dark blue overlay at 55% opacity. Centered content: small gold pill label "Final International University", h1 "Congolese Community of Final", one-line subtitle, two buttons side by side — primary "Join the community" and secondary outline "Discover the community". Below them a thin row of three inline stats: members, events, years active.
2. Stats band on white: four large numbers with labels — Registered members · Events organized · Photos archived · Alumni. Numbers in #0063C6, labels in #64748B, thin dividers between them.
3. "About the community" section, two columns: left a rounded photo collage, right a heading, two short paragraphs and a "Read our story" text link with arrow.
4. "Upcoming events" section: heading with a "See all events" link on the right, then three event cards in a row. Each card: 16:9 image, gold date badge overlapping the image corner, event title, location with a pin icon, short excerpt, "Learn more" link.
5. Community lifecycle strip, on a #7BBCFF-tinted background: a horizontal timeline with 5 steps connected by arrows — "Discover", "Register", "Participate", "Vote", "Give back as alumni". Each step has a circular icon in white with a blue glyph, a short title and one line of text.
6. "Latest updates" section: one large featured news card on the left, three compact list items with thumbnails on the right.
7. "Memories" gallery teaser: a masonry grid of 6 photos, slight hover zoom, a "View full gallery" button centered underneath.
8. Final CTA band, solid #0063C6, white text: "Not registered yet?" heading, one supporting line, a gold "Register now" button.
9. Standard footer.

Constraints: no carousel, no video background, no stock-photo corporate handshakes. Sections must alternate white and #F8FAFC backgrounds.
```

---

## PROMPT 2 — Community (About) page

```
Screen: Community page (web).

Goal: explain who the community is, its mission and its values.

Layout:
1. Compact page header band in #0063C6 with white breadcrumb "Home / Community" and h1 "Our Community", plus one subtitle line.
2. Intro section: two columns, left a photo, right three paragraphs of history.
3. Mission / Vision: two large side-by-side cards, each with a circular light-blue icon badge, a title and a paragraph. One card carries a subtle gold left border.
4. "Our values" grid: six small cards, three per row — icon, value name, one sentence.
5. "The community in numbers" band: four counters on a light blue background.
6. "Our journey" vertical timeline: five milestones with year, title and one line each, connected by a vertical blue line with gold dots.
7. CTA band and standard footer.
```

---

## PROMPT 3 — Government page

```
Screen: Government page (web).

Goal: present the elected officials of the current mandate, and give access to previous mandates.

Layout:
1. Page header band, breadcrumb, h1 "Community Government", subtitle "Mandate 2025-2026".
2. President spotlight: a wide horizontal card, portrait photo on the left (rounded, gold ring), on the right the name, the role "President" in blue uppercase, faculty and year, a short quote, and small social icon buttons.
3. Executive grid: 8 member cards, four per row. Each card: square portrait, name, role, faculty, and a small icon-only contact button. Cards lift slightly on hover.
4. "Organisation chart" section: a simple hierarchical diagram, President at top, then Vice-President and Secretary General, then the other roles, connected with thin lines in #7BBCFF.
5. "Previous mandates" section: a horizontal row of year chips (2024-2025, 2023-2024...) that filter an archive list below, showing past teams as compact rows with small avatars.
6. Footer.
```

---

## PROMPT 4 — Registration (census) form

```
Screen: Member registration / census form (web).

Goal: convert a new student into a registered member. The form is long, so it must feel light and never intimidating.

Layout:
1. Split screen. Left column (40%), sticky, background #0063C6: white heading "Join the Community", three reassurance bullets with check icons ("Get event invitations", "Appear in the member directory", "Vote in community elections"), and small print "Your data stays inside the community".
2. Right column (60%), white, scrollable: a 4-step progress indicator at the top (Identity · Studies · Contact · Confirmation) with the active step in blue, completed steps in gold with a check.
3. Step 1 visible: form fields grouped in labeled sections with a light divider between them — First name, Last name, Date of birth, Gender (segmented control), City of origin in DR Congo (select), Passport/ID number.
4. Studies section: University faculty (select), Department, Year of study (select), Student ID number, Arrival year at Final, Expected graduation year.
5. Contact section: Email (with helper text "A verification code will be sent to this address"), Phone with country code selector, WhatsApp number, Instagram (optional).
6. Privacy block on a #F8FAFC background: a short paragraph and three toggle switches — "Show my profile in the member directory", "Show my email to other members", "Show my phone to other members". First one on by default, the other two off.
7. Consent checkbox and a full-width primary "Submit my registration" button, with a secondary "Save and continue later" text link under it.
8. Show one field in an error state with a red inline message, to demonstrate validation styling.

Constraints: single column inside the form (max two fields side by side for short ones), labels above inputs, no placeholder-only fields.
```

---

## PROMPT 5 — Email verification (OTP)

```
Screen: Email verification (web, centered card, no sidebar).

Goal: the member enters the 6-digit code received by email.

Layout: page background #F8FAFC. A centered white card, max 480px, 12px radius, soft shadow. Inside: community logo at the top, h2 "Verify your email", a line of muted text "We sent a 6-digit code to jean.mukendi@final.edu.tr", six separate square code inputs in a row with the first one focused (blue ring), a full-width primary "Verify" button, a countdown line "Resend code in 00:47" in muted text, and a "Change email address" text link at the bottom. Above the card, a small step indicator showing step 2 of 3 complete. Also show, as a second variant below, the success state: green check circle, "Email verified", "Your registration is pending admin approval" and a primary "Go to my profile" button.
```

---

## PROMPT 6 — Member profile / dashboard

```
Screen: Logged-in member profile (web).

Goal: the member's personal home inside the portal.

Layout:
1. Cover band in #0063C6 (about 180px tall), with the member avatar (circular, white ring, 120px) overlapping the bottom edge on the left.
2. Under the cover: name in h1, faculty and year in muted text, and two status pills — a blue "Student" pill and a gold "Verified member" pill. On the right, an outline "Edit profile" button.
3. Tab bar under the header: Overview · My events · My documents · Privacy settings. Active tab underlined in blue.
4. Overview tab content: two columns.
   Left (65%): "Membership card" widget — a horizontal card with the brand blue background, the member name, membership number, QR code on the right, and the DR Congo flag colors as a thin bottom strip. Under it, "Your upcoming events" as three compact rows with date blocks, then "Recent community news" as three list items.
   Right (35%): a checklist card "Complete your profile" with 4 items and a progress bar, a card showing voting eligibility with a green check and "You are eligible to vote in the 2026 election", and a small card linking to the member directory.
5. Footer.
```

---

## PROMPT 7 — Members directory

```
Screen: Members directory (web, members-only area).

Goal: let a member find and connect with other members. This is the "meet the others" step of the community lifecycle.

Layout:
1. Page header band with h1 "Member Directory" and a muted line "412 registered members".
2. Sticky search and filter bar: a wide search input with a magnifier icon, then four dropdown filters — Faculty, Year of study, Arrival year, City of origin — and a segmented status filter on the right with three options: All · Students · Alumni. A "Clear filters" text link.
3. Active filter chips row below (removable chips with an x).
4. Results grid: 4 columns of member cards. Each card: circular portrait, name, faculty, year, a status pill (blue "Student" or gold "Alumni"), a small city label with a pin icon, and two icon buttons at the bottom (message, view profile). Hover raises the card and shows a light blue border.
5. Show one card in a "private profile" state: blurred placeholder avatar, name replaced by "Private member", muted styling — to demonstrate the opt-in privacy rule.
6. Pagination centered at the bottom, blue active page.
7. Footer.

Constraints: no phone numbers or emails visible on cards. Keep the grid airy.
```

---

## PROMPT 8 — Alumni space

```
Screen: Alumni page (web).

Goal: show that graduates stay part of the community, and make their careers visible.

Layout:
1. Header band with a warmer treatment: #0063C6 background with a subtle gold diagonal accent, h1 "Alumni Network", subtitle "They studied at Final. They are still part of the community."
2. Stats row: Alumni count · Countries · Fields of work · Available mentors, with gold numbers on white cards.
3. Featured alumnus block: large horizontal card, portrait left, on the right the name, graduation year in a gold pill, current position and city, a two-sentence quote in larger italic type, and a "Read the interview" link.
4. Filter row: Graduation year (dropdown), Field of work (dropdown), Country (dropdown), plus a toggle "Available as mentor only".
5. Alumni grid, three per row. Cards are wider than directory cards: portrait on the left third, on the right the name, "Class of 2023" gold badge, job title, company, city with a flag icon, and a small light-blue "Mentor" pill when applicable.
6. Bottom CTA band: "Graduated? Update your status" with an outline white button.
7. Footer.
```

---

## PROMPT 9 — Mentorship

```
Screen: Mentorship page (web).

Goal: connect current students with alumni mentors.

Layout:
1. Header band, h1 "Mentorship", one-line subtitle.
2. "How it works" strip: three numbered steps in a row with circular gold numbers — "Find a mentor", "Send a request", "Meet and grow". Thin connecting arrows between them.
3. Two-column layout below.
   Left sidebar (280px): filter panel in a white card — checkbox groups for Field (Engineering, Medicine, Business, Law, IT...), Availability (Weekly, Monthly, One-off), Format (In person, Online), and a "Reset" link.
   Right: list of mentor cards, one per row. Each row: portrait, name and graduation year, field tags as light blue pills, a two-line bio, availability line with a clock icon, and a primary "Request mentorship" button on the right.
4. A modal preview at the end: "Request mentorship" dialog with the mentor's name, a topic select, a message textarea, and Cancel / Send buttons.
5. Bottom band: "Are you an alumnus? Become a mentor" with a gold button.
6. Footer.
```

---

## PROMPT 10 — Events list + event detail

```
Screen A: Events listing page (web).

Layout:
1. Header band, h1 "Events", subtitle.
2. Toggle at the top right of the content: Upcoming | Past, as a segmented control. Next to it a small icon toggle for grid view / calendar view.
3. Featured next event: full-width horizontal card, large image on the left, on the right a gold date badge, title, location, short description, a countdown row (days / hours / minutes as three small boxes) and a primary "Register for this event" button.
4. Grid of event cards, three per row: image, gold date badge on the image corner, category pill (Cultural, Sports, Academic, Social) in light blue, title, location with pin icon, attendee count with a people icon.
5. "Past events" section below with the same cards in a muted treatment (grayscale image, "Archived" gray pill, "View photos" link instead of registration).
6. Pagination and footer.

Screen B: Event detail page (web).

Layout:
1. Full-width hero image (~50vh) with a dark overlay, breadcrumb, event title in h1, and a row of three inline meta items: date, time, location, each with an icon.
2. Two-column body. Left (65%): "About this event" paragraphs, a "Program" vertical timeline with time slots on the left in blue and activity names on the right, a "Dress code" note in a light blue callout box, and an embedded map placeholder.
3. Right (35%), sticky card: date block, location, organizer avatar and name, attendee avatars stacked with a "+34 going" label, a full-width primary "Register" button, an outline "Add to calendar" button, and share icons.
4. "Photos from this event" masonry strip (only for past events).
5. "You might also like" — three related event cards.
6. Footer.
```

---

## PROMPT 11 — Photo gallery

```
Screen: Gallery page (web).

Goal: the visual memory of the community. Photos must be the hero, chrome must disappear.

Layout:
1. Slim header band, h1 "Memories", subtitle "Every moment of our community, archived".
2. Filter row: album chips (All · Congolese Day · Independence Day · Welcome Party · Sports · Graduation) with the active chip filled in blue, plus a year dropdown on the right.
3. Album section: for each album, a small heading with the album name, date and photo count, then a masonry grid of photos with varied heights, 4 columns, 8px gaps, subtle zoom and a light blue overlay on hover.
4. A lightbox preview state: full-screen dark overlay, large photo centered, left/right arrows, close x top right, and a bottom bar with the photo caption, album name and download and share icons.
5. "Load more photos" outline button centered.
6. Footer.

Constraints: minimal text, no card borders around photos, no captions on the grid itself.
```

---

## PROMPT 12 — News / announcements

```
Screen A: News listing (web).
Layout: header band with h1 "News & Announcements". A featured article as a large two-column block (image left, category pill, title in h2, excerpt, author avatar with name and date, "Read more" link). Below, a two-column list of article cards: thumbnail on top, category pill (Announcement in blue, Event in gold, Urgent in red), title, two-line excerpt, date and reading time in muted text. A right sidebar (300px) with: a "Subscribe to announcements" card with an email input and a blue button, a "Categories" list with counts, and a "Most read" list of five numbered items. Pagination, footer.

Screen B: Article detail (web).
Layout: narrow centered column, max 760px. Breadcrumb, category pill, h1 title, author row (avatar, name, role, publication date, reading time), a wide featured image, then the article body with clear typography, a pull quote styled with a thick blue left border, and an image with a caption. At the end: share buttons, a tag row, an author card, and three related articles as horizontal rows. Footer.
```

---

## PROMPT 13 — Election hub and candidates

```
Screen A: Election hub (web).

Goal: make the ongoing election legible and trustworthy at a glance.

Layout:
1. Header band in #0063C6 with h1 "Community Government Election 2026" and a live status pill in gold: "Voting is open".
2. Countdown row: four large boxes (Days, Hours, Minutes, Seconds) on white cards with blue numbers, plus the closing date in muted text.
3. Eligibility card: a bordered white card with a green check icon, "You are eligible to vote", the member name and registration number, and a large primary "Go to the ballot" button. Show a second variant below in a disabled state: gray, an info icon, "Registration pending approval — you cannot vote yet".
4. Timeline strip with four phases: Registration closed · Candidacies · Voting open · Results, with the current phase highlighted in blue and completed ones in gold with a check.
5. "Positions to be filled" grid: six cards, each with an icon, the position name (President, Vice-President, Secretary General, Treasurer, Cultural Officer, Communication Officer) and the number of candidates.
6. "Election rules" accordion with five collapsible items.
7. Footer.

Screen B: Candidates page (web).

Layout: header band with h1 "Candidates". A sticky tab bar of positions (President · Vice-President · Secretary General...). For the selected position, candidate cards two per row, generously sized: large portrait on the left, on the right the candidate number in a gold circle, name, faculty and year, a three-item manifesto list with check icons, and two buttons — outline "Read full program" and primary "View profile". A comparison strip at the bottom: "Compare candidates" opening a table with rows for Faculty, Year, Experience, Key promise. Footer.
```

---

## PROMPT 14 — Voting authentication and ballot

```
Screen A: Voting authentication (web, no navigation menu, minimal chrome — this is a secure flow).

Layout: page background #0063C6 with a subtle pattern. A centered white card, max 460px. Inside: a shield icon in a light blue circle, h2 "Secure voting access", a muted line "Confirm your identity to receive your voting token", fields for Student ID number and Registration email, an info callout in light blue explaining "A one-time voting link will be sent to your email. Your vote will never be linked to your identity.", and a full-width primary "Send my voting link" button. Below the card, three small trust indicators in white text with icons: "Anonymous ballot", "One vote per member", "Encrypted".

Screen B: Ballot (web, no navigation menu).

Goal: zero ambiguity, zero distraction, impossible to misclick.

Layout:
1. Slim top bar: community logo on the left, "Presidential Election 2026" centered, and a session timer "12:45 remaining" on the right in muted text.
2. Progress indicator: "Position 1 of 6" with a thin blue progress bar.
3. h2 "Choose your candidate for President". One line of muted instruction "Select one candidate. You can review your choices before submitting."
4. Candidate options as large selectable cards stacked vertically: each card has a radio control on the left, a portrait, the candidate number in a gold circle, the name, faculty and a one-line slogan. The selected card gets a 2px #0063C6 border, a very light blue fill and a check icon. Include a final "Blank vote" option styled more soberly.
5. Bottom sticky bar: outline "Previous" on the left, primary "Next position" on the right.
6. Then show the review state: a summary card listing all six positions with the chosen candidate and an "Edit" link per row, a red-bordered warning callout "Once submitted, your vote cannot be changed", and a large primary "Submit my vote" button.
7. Then the confirmation state: full-page centered, a large gold check circle, "Your vote has been recorded", a receipt code in monospace, and a "Back to the portal" button.

Constraints: no navigation menu, no footer links, nothing clickable except the ballot itself.
```

---

## PROMPT 15 — Results

```
Screen: Election results (web).

Goal: transparent, readable, celebratory but sober.

Layout:
1. Header band with h1 "Election Results 2026" and a gold pill "Final results — certified".
2. Turnout row: three stat cards — Registered voters, Votes cast, Turnout percentage with a circular progress ring in blue.
3. Winner spotlight per position: a wide card with a gold left border, a laurel or star icon, the portrait, "Elected President", the name, the vote count and percentage.
4. Detailed results per position: for each position, a white card containing the position title and a list of candidates as horizontal bar rows — portrait thumbnail, name, a horizontal bar filled proportionally (winner bar in #0063C6, others in #7BBCFF), the percentage on the right and the raw vote count in muted text.
5. A donut chart card showing the vote distribution for the presidential race, with a legend.
6. "Download the official report (PDF)" outline button with a document icon.
7. Footer.
```

---

## PROMPT 16 — Admin dashboard

```
Screen: Admin dashboard (web, authenticated back office).

Goal: give community admins control over members, events and elections.

Layout:
1. Fixed left sidebar (260px), background #0063C6, white text: logo at the top, then nav items with icons — Dashboard, Members, Events, News, Gallery, Documents, Elections, Emails, Settings. Active item has a white background block with blue text and a gold left indicator bar. At the bottom, the admin avatar, name and a "Sign out" link.
2. Top bar: page title "Dashboard", a search input, a notification bell with a red dot, and the admin avatar.
3. KPI row: four stat cards — Total members, Pending approvals (with a red count), Upcoming events, Votes cast today. Each with an icon in a light blue square, the number in large blue type, and a small trend line.
4. Two-column content.
   Left (65%): a line chart card "Registrations over time" with a period selector; below it a "Pending member approvals" table with columns Photo, Name, Faculty, Date, and an action column with a green check icon button and a red x icon button per row, plus a "View all" link.
   Right (35%): an "Election status" card with a live turnout ring and an "Open / Close voting" toggle guarded by a red button; a "Recent activity" feed with small avatars and timestamps; a "Quick actions" card with three outline buttons — Create event, Publish announcement, Export members.
5. Data table styling: sticky header row, zebra rows, status pills, row hover in #F8FAFC.
```

---

## PROMPT 17 — "New at Final?" onboarding page

```
Screen: Onboarding page for newcomers (web).

Goal: the entry point of the community lifecycle — a student who just arrived must know exactly what to do first.

Layout:
1. Warm hero: light blue (#7BBCFF) tinted background, an illustration or photo of a student arriving with luggage on the right, on the left h1 "New at Final?", a supportive paragraph and a primary "Start here" button.
2. "Your first steps" vertical stepper: five numbered steps, each a white card with a gold circled number, a title, two lines of text and a small action link — 1. Join the WhatsApp group, 2. Register in the community, 3. Meet your faculty seniors, 4. Attend your first event, 5. Explore the member directory.
3. "Useful contacts" grid: six compact cards with an icon, a role (Housing, University registration, Residence permit, Health insurance, Bank account, Emergency), one line of guidance and a contact link.
4. FAQ accordion with eight questions.
5. A closing band: "Still lost? Ask the community" with an outline button to the contact page.
6. Footer.
```

---

## PROMPT 18 — Contact page

```
Screen: Contact page (web).

Layout: header band with h1 "Contact us". Two columns. Left (55%): a contact form in a white card — Full name, Email, Subject (select: General question, Membership, Event, Election, Report a problem), Message textarea, and a primary "Send message" button. Right (45%): a stack of three cards — a "Reach us directly" card with email, WhatsApp group and Instagram rows each with an icon and a chevron; an "Office hours" card with a small weekly table; and a "Find us" card with a map placeholder and the campus address. Below both columns, a row of four government member avatars under the heading "Or contact a board member directly". Footer.
```

---

## PROMPT 19 — Mobile navigation and key mobile screens

```
Generate the mobile versions (390px width) of the following screens, keeping the same design system:
1. Home — hero with stacked buttons, a horizontally scrollable events row, stats in a 2x2 grid.
2. Mobile navigation drawer — full-screen white overlay sliding from the right, large tappable links, the language switcher at the bottom, and a full-width blue "Join the community" button.
3. Member directory — single-column cards with the portrait on the left, filters collapsed into a "Filters" button opening a bottom sheet.
4. Ballot — one candidate card per row, full width, sticky bottom bar with the "Next" button.
5. Bottom tab bar for logged-in members: Home, Events, Directory, Election, Profile — icons with labels, active item in #0063C6.

Constraints: minimum 44px touch targets, no hover-dependent interactions, thumb-reachable primary actions.
```

---

## Prompts d'itération utiles

À réutiliser tels quels quand un écran ne va pas :

- `Reduce the visual noise: fewer borders, more whitespace, and keep gold only on the primary CTA.`
- `The hero is too tall and too generic. Make it 60vh and add a real sense of place — a campus photo, not an abstract gradient.`
- `Make the hierarchy stronger: the h1 should be clearly larger than the section titles, and the muted text should be visibly lighter.`
- `This looks like a generic SaaS template. Make it feel institutional and student-run: more editorial, less product-marketing.`
- `Keep the layout but apply the exact color tokens from my design system — some elements are using colors I did not specify.`
- `Show the empty state and the error state of this screen.`
- `Now export this screen and generate a matching DESIGN.md so I can reuse these tokens in other tools.`

---

## Ordre de génération conseillé

| Priorité | Écrans | Pourquoi |
|---|---|---|
| 1 | 0, 1, 4, 5 | Le design system + le tunnel d'entrée : c'est ce qui valide le style |
| 2 | 10, 11, 12 | Le contenu vivant du hub (événements, souvenirs, news) |
| 3 | 6, 7, 8, 9 | Les modules "communauté permanente" (profil, annuaire, alumni, mentorat) |
| 4 | 13, 14, 15 | L'élection, qui doit inspirer confiance |
| 5 | 2, 3, 16, 17, 18, 19 | Le reste et les déclinaisons mobiles |

Une fois les écrans validés dans Stitch, exporte en HTML/Tailwind : ça donne à Claude Code des références concrètes pour construire les vues PHP du portail, en gardant la stack décrite au cahier des charges (PHP 8.2 + PDO + MySQL, Tailwind, JS vanilla).
