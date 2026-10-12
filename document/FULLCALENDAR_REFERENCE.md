# FullCalendar 5.11.5 reference guide

**Project:** offline PHP 7.4 + MariaDB calendar laboratory  
**Project directory:** `E:\www\fullcalendar`  
**Apache document root:** `E:\www\fullcalendar\public`  
**Local address:** <http://localhost:4040/>  
**Library version:** FullCalendar / Scheduler **5.11.5**  
**Guide date:** October 11, 2026

This guide documents the installed project and the main FullCalendar v5 APIs used to extend it. Sections labeled **Library example** illustrate FullCalendar behavior; they do not automatically save changes to MariaDB. Sections labeled **Project behavior** describe the supplied application. Examples use ES5-style JavaScript for the required older browsers.

FullCalendar displays schedules in the browser. PHP authenticates requests and manages database records. MariaDB stores calendar definitions, event definitions, resources, and share permissions. Recurrence controls and sharing are application features built around FullCalendar.

This is a practical reference, not a reproduction of every upstream API page. Use the [official v5 documentation](https://legacy.fullcalendar.io/v5) for the complete index. Avoid substituting v6/v7 examples without checking their differences.

## Contents

1. [Installation and login](#1-installation-and-login)
2. [Files and architecture](#2-files-and-architecture)
3. [Offline assets and readable source](#3-offline-assets-and-readable-source)
4. [Calendar initialization](#4-calendar-initialization)
5. [Views and navigation](#5-views-and-navigation)
6. [Toolbar and layout](#6-toolbar-and-layout)
7. [Event data](#7-event-data)
8. [Dates, exclusive ends, and time zones](#8-dates-exclusive-ends-and-time-zones)
9. [Loading events](#9-loading-events)
10. [Create, edit, and delete](#10-create-edit-and-delete)
11. [Recurring events through the interface](#11-recurring-events-through-the-interface)
12. [Simple recurrence and RRule](#12-simple-recurrence-and-rrule)
13. [Series editing and exceptions](#13-series-editing-and-exceptions)
14. [Selection, dragging, and resizing](#14-selection-dragging-and-resizing)
15. [Business hours and constraints](#15-business-hours-and-constraints)
16. [Resources and timeline views](#16-resources-and-timeline-views)
17. [Rendering and callbacks](#17-rendering-and-callbacks)
18. [JavaScript method reference](#18-javascript-method-reference)
19. [Locales, styling, and printing](#19-locales-styling-and-printing)
20. [PHP API reference](#20-php-api-reference)
21. [Database reference](#21-database-reference)
22. [Sharing and permissions](#22-sharing-and-permissions)
23. [Import, export, and backups](#23-import-export-and-backups)
24. [Premium licensing and Standard-only use](#24-premium-licensing-and-standard-only-use)
25. [Old-browser acceptance testing](#25-old-browser-acceptance-testing)
26. [Deployment and maintenance](#26-deployment-and-maintenance)
27. [Troubleshooting](#27-troubleshooting)
28. [Extension recipes and limitations](#28-extension-recipes-and-limitations)

## 1. Installation and login

1. Extract the deployment ZIP into `E:\www\fullcalendar`, keeping `public`, `tools`, and the root PHP files together.
2. Keep your existing Apache virtual host pointed at `E:\www\fullcalendar\public` on port 4040.
3. Start Apache and MariaDB in XAMPP.
4. Update `config.php` for your existing `fullcalendar` database.
5. On a fresh installation, visit <http://localhost:4040/setup.php>, enter the configured owner password, and install the tables.
6. Open <http://localhost:4040/> and log in. No username is required.

Example configuration, using placeholders for credentials:

```php
<?php
return [
    'dsn' => 'mysql:host=127.0.0.1;dbname=fullcalendar;charset=utf8mb4',
    'user' => 'YOUR_DATABASE_USER',
    'password' => 'YOUR_DATABASE_PASSWORD',
    'admin_password' => 'YOUR_OWNER_PASSWORD',
    'scheduler_license_key' => '',
];
```

The originally supplied owner password is `change-this-password`. If you have edited `admin_password`, use your new value. The owner password is separate from the MariaDB password. Changing it does not invalidate already authenticated PHP sessions.

Setup uses `CREATE TABLE IF NOT EXISTS` for `fc_` tables and adds demo data only when `fc_calendars` is empty. It does not create the MariaDB database, erase existing data, or migrate existing table definitions. PHP must have PDO MySQL enabled.

If your virtual host requires a custom hostname, replace `localhost` in this guide with that hostname.

## 2. Files and architecture

| File or directory | Responsibility |
| --- | --- |
| `config.php` | Database credentials, owner password, Premium key |
| `index.php` | Main page template and script/style references |
| `api.php` | Session authentication, permissions, CRUD, sharing |
| `setup.php` | Table installation and initial demo records |
| `schema.sql` | MariaDB table definitions |
| `recurrence-fields.php` | Recurrence form HTML |
| `public/index.php` | Public wrapper loading the root page template |
| `public/api.php` | Public wrapper loading the root API |
| `public/setup.php` | Public wrapper loading root setup |
| `public/weekly-recurrence.php` | Monday/Wednesday sample page |
| `public/advanced-recurrence.php` | Daily, fortnightly, and monthly samples |
| `public/app.js` | Calendar initialization, editor, API requests, sharing |
| `public/recurrence.js` | Recurrence form population, validation, serialization |
| `public/app.css` | Application layout and print styles |
| `public/vendor/` | Locally stored libraries, licenses, checksums |
| `tools/download-assets.*` | Optional asset restoration utilities |
| `tools/test-*.js` | Optional developer validation scripts |
| `README.md` | Installation and abbreviated feature checklist |

Request flow:

```text
Browser at localhost:4040
  -> public/index.php -> root index.php template
  -> local scripts and styles
  -> public/api.php -> root api.php
  -> session/CSRF/permission checks
  -> PDO -> MariaDB fullcalendar database
  -> JSON response -> FullCalendar updates
```

Root implementation and configuration files are outside the public document root. Do not move only the public directory to a different server: its PHP wrappers require the files one directory above it.

## 3. Offline assets and readable source

All runtime scripts and styles are local. The application does not need internet access after the assets are installed. Apache and MariaDB must still be running; the application is not a standalone file or a disconnected database client.

The page loads these files in order:

| Asset | Purpose |
| --- | --- |
| `vendor/polyfills.js` | core-js compatibility helpers |
| `vendor/main.js` | Readable Scheduler 5.11.5 browser bundle |
| `vendor/locales-all.js` | FullCalendar locale definitions |
| `vendor/moment.min.js` | Moment date library |
| `vendor/moment-timezone-with-data.min.js` | Named zones and bundled zone data |
| `vendor/moment-timezone-main.js` | FullCalendar named-zone connector |
| `vendor/rrule.min.js` | RRule recurrence engine |
| `vendor/rrule-main.js` | FullCalendar RRule connector |
| `recurrence.js` | Application recurrence form code |
| `app.js` | Application calendar code |

The connector scripts are browser-global builds, originally named `main.global.js` upstream. Module builds containing `import` declarations cannot be used in ordinary script tags in this old-browser setup. [Official v5 script-tag installation](https://legacy.fullcalendar.io/v5/initialize-globals)

`vendor/fullcalendar-standard.js` is a separate readable Standard bundle included for reference. Load either it or Scheduler's `main.js`, never both. `.min.js` files are compressed distributions; the readable bundles provide formatting and descriptive symbols, although they are still compiled distributions rather than the original TypeScript repository.

To restore assets while internet access is available:

```powershell
# From the project root:
.\tools\download-assets.ps1

# Alternative if Node is available and PowerShell TLS stalls:
node tools/download-assets.js
```

Node is not required by the deployed PHP application. The Node downloader skips existing nonempty files. Its generated checksums record local file hashes; they are not independently signed upstream attestations.

## 4. Calendar initialization

**Library example:** a standalone calendar with static data:

```javascript
var calendar = new FullCalendar.Calendar(
    document.getElementById('calendar'),
    {
        initialView: 'dayGridMonth',
        initialDate: '2026-10-01',
        locale: 'en',
        timeZone: 'local',
        editable: true,
        selectable: true,
        dayMaxEvents: 3,
        events: [
            {
                id: 'example-1',
                title: 'Planning',
                start: '2026-10-12T09:00:00',
                end: '2026-10-12T10:00:00'
            }
        ]
    }
);
calendar.render();
```

This example assumes the calendar element and assets already exist. It does not authenticate or save anything. In the actual project, `public/app.js` waits for `state` from PHP, derives editing permissions, configures database feeds, and then renders the calendar. Do not paste a second initialization on top of it.

## 5. Views and navigation

| View identifier | Display | Installed page selector |
| --- | --- | --- |
| `dayGridMonth` | Month with event rows | Yes |
| `timeGridWeek` | Timed weekly columns | Yes |
| `timeGridDay` | Timed daily column | Yes |
| `listWeek` | Weekly event list | Yes |
| `listMonth` | Monthly event list | Yes |
| `fourDays` | Project custom four-day TimeGrid | Yes |
| `resourceTimeGridDay` | Timed day separated by resource | Yes, Premium |
| `resourceTimeGridWeek` | Timed week separated by resource | Yes, Premium |
| `resourceDayGridMonth` | Month separated by resource | Yes, Premium |
| `resourceTimelineDay` | Horizontal resource timeline | Yes, Premium |
| `resourceTimelineWeek` | Weekly resource timeline | Yes, Premium |
| `resourceTimelineMonth` | Monthly resource timeline | Yes, Premium |

Common navigation calls:

```javascript
calendar.changeView('timeGridWeek');
calendar.gotoDate('2026-10-12');
calendar.prev();
calendar.next();
calendar.prevYear();
calendar.nextYear();
calendar.today();
var currentDate = calendar.getDate();
var currentView = calendar.view;
```

Custom view configuration used by this project:

```javascript
views: {
    fourDays: {
        type: 'timeGrid',
        duration: { days: 4 },
        buttonText: '4 days'
    }
}
```

The visible month grid may include days from adjacent months. The view's active range can therefore be larger than the named calendar month. `datesSet` is useful for reporting the current range or synchronizing surrounding interface elements.

## 6. Toolbar and layout

**Library options example:**

```javascript
headerToolbar: {
    left: 'prev,next today',
    center: 'title',
    right: 'dayGridMonth,timeGridWeek,timeGridDay,listWeek'
},
height: 'auto',
nowIndicator: true,
navLinks: true,
weekNumbers: true,
weekends: true,
dayMaxEvents: 3
```

| Option | Use |
| --- | --- |
| `initialView` | Starting view |
| `initialDate` | Starting navigation date |
| `headerToolbar`, `footerToolbar` | Button placement and title |
| `buttonText`, `customButtons` | Labels and custom actions |
| `height`, `contentHeight` | Outer/calendar content sizing |
| `aspectRatio`, `expandRows` | Layout proportions and row expansion |
| `fixedWeekCount` | Month grid row count behavior |
| `showNonCurrentDates` | Adjacent-month date visibility |
| `dayMaxEvents`, `dayMaxEventRows` | Event limiting and more links |
| `slotMinTime`, `slotMaxTime` | Visible time-axis boundaries |
| `slotDuration`, `slotLabelInterval` | Grid resolution and labels |
| `scrollTime` | Initial time-axis scroll position |
| `eventTimeFormat` | Event time formatting |
| `validRange` | Allowed calendar date range |

Many global options can change with `calendar.setOption(name, value)`. Use navigation methods for date/view changes and event-source methods for source changes. Not every setting supports dynamic mutation; consult [dynamic options](https://legacy.fullcalendar.io/v5/dynamic-options).

The project's Advanced calendar options panel accepts JSON, not JavaScript functions. It reserves persistence handlers and permission-related settings. These changes are not stored in MariaDB or carried across page reloads.

## 7. Event data

**Project-compatible event input:**

```json
{
  "title": "Project meeting",
  "start": "2026-10-12T09:00:00+09:00",
  "end": "2026-10-12T10:00:00+09:00",
  "allDay": false,
  "color": "#2563eb",
  "resourceIds": ["1"],
  "extendedProps": {
    "description": "Discuss the weekly schedule",
    "category": "meeting"
  }
}
```

| Field | Meaning |
| --- | --- |
| `id` | FullCalendar identifier; the API supplies the database ID as a string |
| `title` | Display name; required by this project's API |
| `start`, `end` | Date/time boundaries; end is exclusive |
| `allDay` | Date-only/all-day rendering |
| `groupId` | Connects events for coordinated drag/resize behavior |
| `color` | Input shortcut for background and border colors |
| `backgroundColor`, `borderColor`, `textColor` | Individual display colors |
| `display` | `auto`, `block`, `list-item`, `background`, `inverse-background`, `none` |
| `editable` | Per-event editing override |
| `startEditable`, `durationEditable` | Fine-grained move/resize overrides |
| `constraint`, `overlap` | Per-event interaction rules |
| `resourceId`, `resourceIds` | Premium resource assignment |
| `url` | Optional link metadata; PHP accepts only HTTP(S) URLs |
| `extendedProps` | Application-specific metadata |

The live Event Object is parsed data rather than the original input JSON. Its fields should be changed through methods such as `setProp` and `setDates`. Store application metadata under `extendedProps`. [Event Object reference](https://legacy.fullcalendar.io/v5/event-object)

**Project behavior:** clicking an event always opens the editor and prevents default URL navigation. A saved `url` is not automatically followed. The API injects `extendedProps.version`; this is a concurrency field, not a user-editable property.

## 8. Dates, exclusive ends, and time zones

### Exclusive end dates

An all-day event covering October 12 and 13 is:

```json
{
  "title": "Two-day visit",
  "start": "2026-10-12",
  "end": "2026-10-14",
  "allDay": true
}
```

October 14 is not included. A timed meeting from 09:00 until 10:00 has an end of 10:00. Apply the same exclusive-boundary interpretation to selection ranges and event-feed ranges.

### Accepted date conventions

| Example | Intended interpretation |
| --- | --- |
| `2026-10-12` | Calendar date, typically all-day |
| `2026-10-12T09:00:00` | Wall-clock time in the selected calendar zone |
| `2026-10-12T00:00:00Z` | Absolute UTC instant |
| `2026-10-12T09:00:00+09:00` | Instant with explicit UTC offset |

The project API accepts ISO-shaped start/end strings and verifies date parsing and end-after-start. It does not normalize every saved value into UTC or store a calendar timezone separately.

### Calendar time zone

`local` uses the browser's zone, `UTC` displays UTC, and named zones such as `Asia/Tokyo` use the installed Moment Timezone connector. Offset-free input is interpreted within the configured zone; offset-bearing input identifies an instant. [FullCalendar time-zone behavior](https://legacy.fullcalendar.io/v5/timeZone)

**Project limitation:** changing the zone dropdown changes display/parsing context, not a persisted calendar-level timezone. Interface recurrence rules use offset-free `dtstart`. Consequently a rule can display at the same wall-clock time in different selected zones rather than represent one fixed global instant. For production schedules, define and persist a schedule zone before relying on cross-zone recurrence behavior.

`Date.toISOString()` always produces UTC. Do not use its first ten characters as a universal local-calendar date conversion. For browser-local dates, use local date components; for named calendar zones, use the calendar/date-library formatting appropriate to that zone.

## 9. Loading events

FullCalendar can consume an array, a JSON feed, or a callback function. This application uses a function so it can attach calendar IDs and share tokens through the existing request helper. The callback receives the needed range and success/failure callbacks. [Function event sources](https://legacy.fullcalendar.io/v5/events-function)

**Library example using the project's internal request helper:**

```javascript
events: function(info, success, failure) {
    request('events', null, success, failure);
}
```

`request` is private to the `app.js` closure; this snippet belongs inside that file. The installed callback also keeps original definitions in `raw` and disables drag/resize for recurring definitions.

**Project behavior:** `GET api.php?action=events&calendar=1` returns all definitions in that calendar. It does not filter by the requested visible range. FullCalendar expands recurrence for the current view in the browser. This is convenient for a test dataset but is not optimized for a large installation.

After a database write, the application calls `calendar.refetchEvents()` to reload IDs, versions, and recurrence definitions. Source additions, removals, and refetches only affect the browser unless a separate API operation persists them.

## 10. Create, edit, and delete

### Through this project's interface

1. Click Add event, or select a date/time range.
2. Enter a title and one-time start/end values, or choose a Repeat schedule.
3. Set All day, color, description, display mode, and optional resource IDs.
4. Click Save.
5. Click an existing event to edit it; Delete removes its database definition.

Save replaces the event payload for an existing database ID. The editor preserves optional advanced properties in its JSON panel. A stale version causes a conflict instead of silently overwriting another window's edit.

### Library-only methods

```javascript
var event = calendar.addEvent({
    id: 'temporary',
    title: 'Browser-only event',
    start: '2026-10-12T09:00:00'
});
event.setProp('title', 'Renamed in browser');
event.setExtendedProp('category', 'training');
event.setDates('2026-10-13T09:00:00', '2026-10-13T10:00:00');
event.remove();
```

These calls do not persist automatically in the supplied project. Its persistence handlers are the editor Save/Delete actions and drag/resize/receive handlers. Adding a generic `eventChange` autosave without restructuring them can produce duplicate saves and version conflicts.

## 11. Recurring events through the interface

Sample pages:

- <http://localhost:4040/weekly-recurrence.php>
- <http://localhost:4040/advanced-recurrence.php>

They share the main calendar's database and login. Example buttons prefill forms; they do not insert records until Save. Pass `?calendar=ID` when you want a sample page to operate on another calendar.

### Monday and Wednesday, 09:00–10:00

1. Click Add event and enter a title.
2. Choose Weekly — choose weekdays.
3. Set First date to `2026-10-01`.
4. Set Repeat every to `1` week.
5. Check Monday and Wednesday.
6. Leave All day unchecked and enter `09:00` / `10:00`.
7. Choose On a date and enter `2026-12-31`, or select Never.
8. Save.

You do not need to clear Start/End manually: the interface disables those inputs while recurrence controls are active and removes one-time boundaries from the generated payload.

### Available controls

| Control | Project behavior |
| --- | --- |
| Does not repeat | Removes recurrence fields; requires a one-time Start |
| Daily | Uses RRule daily frequency |
| Weekly | Uses selected weekday checkboxes |
| Monthly | Uses the first date's day of month |
| Repeat every | Integer interval from 1 to 365 |
| First date | Earliest boundary/anchor for the rule |
| All day | Generates a one-day duration and date-only start |
| Start / end time | `HH:MM`; equal times are rejected |
| End time before start time | Treated as overnight into the next day |
| Never | No recurrence end boundary |
| On a date | Inclusive last calendar date in the form |
| After occurrences | Count from 1 to 10,000 |

For weekly schedules, First date is a lower bound; if it is not a checked weekday, the first occurrence is a subsequent selected day. Monthly schedules beginning on the 31st skip months without a 31st. The form does not implement a “last day of month” mode.

The form's inclusive last date is serialized as `until: LAST_DATE + 'T23:59:59'`. This convention differs from simple recurrence's exclusive `endRecur` boundary.

## 12. Simple recurrence and RRule

### Simple weekly recurrence

FullCalendar's built-in simple recurrence uses weekday numbers, times, and date bounds. Sunday is 0 and Saturday is 6. The ending recurrence boundary is exclusive. [Simple recurrence documentation](https://legacy.fullcalendar.io/v5/recurring-events)

```json
{
  "title": "Weekly meeting",
  "daysOfWeek": [1, 3],
  "startTime": "09:00:00",
  "endTime": "10:00:00",
  "startRecur": "2026-10-01",
  "endRecur": "2027-01-01"
}
```

The project can load these definitions and show them in the recurrence editor. Saving through the interface normally converts a supported simple rule to RRule; the original database ID is retained.

### RRule examples

The installed RRule connector supports rules beyond simple weekly recurrence, and accepts string or object definitions with a separate event duration. [RRule connector reference](https://legacy.fullcalendar.io/v5/rrule-plugin)

**Every second week on Monday and Wednesday:**

```json
{
  "title": "Fortnightly planning",
  "allDay": false,
  "rrule": {
    "freq": "weekly",
    "interval": 2,
    "byweekday": ["mo", "we"],
    "dtstart": "2026-10-12T09:00:00",
    "count": 10
  },
  "duration": "01:00:00"
}
```

The count is ten generated occurrences across both weekdays, not ten weeks.

**Daily all-day reminder:**

```json
{
  "title": "Daily reminder",
  "allDay": true,
  "rrule": {
    "freq": "daily",
    "interval": 1,
    "dtstart": "2026-10-12",
    "count": 10
  },
  "duration": { "days": 1 }
}
```

Weekday code mapping:

| Day | Simple recurrence number | RRule code |
| --- | --- | --- |
| Sunday | 0 | `su` |
| Monday | 1 | `mo` |
| Tuesday | 2 | `tu` |
| Wednesday | 3 | `we` |
| Thursday | 4 | `th` |
| Friday | 5 | `fr` |
| Saturday | 6 | `sa` |

## 13. Series editing and exceptions

The database stores one definition per series, not one row per occurrence. FullCalendar generates visible occurrences from it.

**Project behavior:**

- Clicking an occurrence edits the entire series.
- Deleting an occurrence through the editor deletes the entire series.
- Recurring drag/resize is disabled, and the move API rejects recurring definitions.
- Unsupported advanced RRule definitions are displayed in preserve mode. Leave that mode selected to retain their recurrence definition.
- Choosing another repeat mode replaces recurrence properties, including existing exclusions.

**Advanced-library example:** add an exclusion matching an occurrence start:

```json
{
  "title": "Meeting with one excluded date",
  "rrule": {
    "freq": "weekly",
    "byweekday": ["mo"],
    "dtstart": "2026-10-12T09:00:00",
    "count": 8
  },
  "duration": "01:00:00",
  "exdate": ["2026-10-19T09:00:00"]
}
```

This can be stored using advanced JSON; there is no dedicated exception picker. For “edit only this occurrence,” add an exclusion to the series and create a separate one-time replacement event. That workflow, occurrence identifiers, and exception records need custom implementation; the current editor does not perform it automatically.

## 14. Selection, dragging, and resizing

| Option or callback | Role |
| --- | --- |
| `selectable` | Enables date/time range selection |
| `selectMirror` | Selection preview |
| `dateClick` | Individual date/time click notification |
| `select` | Selected range callback |
| `editable` | Enables supported event movement and resizing |
| `eventResizableFromStart` | Allows start-edge resizing |
| `eventDrop` | Event moved within the calendar |
| `eventResize` | Event duration changed |
| `droppable` | Accepts supported external drags |
| `eventReceive` | External event accepted into this calendar |

In the project, `dateClick` only writes to the callback log; range selection opens the editor. The New task drag source creates a database event after a successful drop.

`eventDrop` supplies the changed event, related events, and `revert()`. `eventResize` provides corresponding resize information and a revert function. Revert when persistence fails so browser and database state do not diverge. [Drop callback](https://legacy.fullcalendar.io/v5/eventDrop), [resize callback](https://legacy.fullcalendar.io/v5/eventResize)

**Project behavior:** grouped events are sent to the `move` endpoint together. The server checks every version and commits them in one transaction. It accepts at most 100 records in a move batch. If a group contains a recurring definition or stale record, the operation fails and the interaction is reverted.

## 15. Business hours and constraints

Business hours provide a visual availability indicator. They become movement/selection restrictions only when constraint settings are also applied.

**Library options example:**

```javascript
businessHours: {
    daysOfWeek: [1, 2, 3, 4, 5],
    startTime: '09:00',
    endTime: '18:00'
},
eventConstraint: 'businessHours',
selectConstraint: 'businessHours',
eventOverlap: false,
selectOverlap: false
```

The project's Business hours checkbox controls shading. Its restriction checkbox separately sets `eventConstraint` and `selectConstraint`. Allow event overlap sets `eventOverlap`, not `selectOverlap`.

`eventAllow` and `selectAllow` enable custom browser-side checks. They require JavaScript functions, so implement them in `app.js`; they cannot be expressed as executable code in the JSON options panel.

These client settings are not PHP booking rules. Manual Save/API requests can bypass browser drag constraints. Enforce availability, overlap, and resource capacity on the server if those are application requirements.

## 16. Resources and timeline views

Resources represent rooms, equipment, employees, or other schedule lanes. The project's API supplies numeric database IDs as string resource IDs. A resource can have a title, optional parent, and additional properties. [Resource Object reference](https://legacy.fullcalendar.io/v5/resource-object)

**Resource input example:**

```json
{
  "title": "Meeting room A",
  "extendedProps": { "capacity": 8 }
}
```

**Event assignment:**

```json
{
  "title": "Room booking",
  "start": "2026-10-12T09:00:00",
  "end": "2026-10-12T10:00:00",
  "resourceIds": ["1", "2"]
}
```

The editor currently accepts resource IDs as comma-separated text. Add resource creates a record; Delete removes it. Resource editing, hierarchy management, capacity enforcement, and a resource dropdown are not implemented as dedicated interface controls.

Deleting a resource leaves event definitions intact. Their saved resource IDs may therefore reference a removed resource until you edit them. Resource IDs are payload values, not foreign-key links from the event table.

Useful Premium calls include `calendar.getResources()`, `getResourceById(id)`, `addResource(input)`, `refetchResources()`, `event.getResources()`, and `event.setResources(ids)`. Direct library mutations do not automatically persist through PHP.

## 17. Rendering and callbacks

The project's `eventDidMount` sets a plain-text tooltip from title and description. Its callback log records navigation, clicking, hovering, and interaction starts.

| Hook | Typical use |
| --- | --- |
| `eventContent` | Custom content for an event |
| `eventClassNames` | Conditional event CSS classes |
| `eventDidMount` | Attach tooltip or DOM behavior |
| `eventWillUnmount` | Clean up attached DOM behavior |
| `dayCellContent`, `dayCellDidMount` | Customize date cells |
| `viewDidMount`, `viewWillUnmount` | View lifecycle behavior |
| `datesSet` | Observe navigation/date-range changes |
| `eventsSet` | Observe the current parsed event collection |
| `eventAdd`, `eventChange`, `eventRemove` | Observe library data mutations |
| `loading` | Show feed loading status |

Render hooks serve different lifecycle purposes. Use content hooks for contents and mount/unmount hooks for attached DOM features. [Event rendering hooks](https://legacy.fullcalendar.io/v5/event-render-hooks)

**Library example using safe text content:**

```javascript
eventContent: function(arg) {
    var span = document.createElement('span');
    span.textContent = arg.event.title;
    return { domNodes: [span] };
}
```

Do not insert untrusted descriptions with `innerHTML`. Preserve existing permission and persistence callbacks when extending the application.

## 18. JavaScript method reference

### Calendar methods

| Method | Use |
| --- | --- |
| `render()` | Render the instance |
| `destroy()` | Remove the instance and its handlers |
| `changeView(name)` | Switch view |
| `gotoDate(date)` | Navigate to a date |
| `prev()`, `next()`, `today()` | Navigate |
| `getDate()` | Read navigation date |
| `getOption(name)`, `setOption(name, value)` | Read/update supported options |
| `updateSize()` | Recalculate layout after container changes |
| `getEvents()` | Read currently parsed events |
| `getEventById(id)` | Find a parsed event |
| `addEvent(input)` | Add a browser-side event |
| `refetchEvents()` | Reload feeds |
| `addEventSource(source)` | Add another event source |
| `getEventSources()` | Inspect sources |
| `select(start, end)`, `unselect()` | Programmatic range selection |
| `scrollToTime(duration)` | Scroll a time-based view |
| `batchRendering(function)` | Group browser rendering changes |

### Event methods

| Method | Use |
| --- | --- |
| `setProp(name, value)` | Change a standard non-date property |
| `setExtendedProp(name, value)` | Change custom metadata |
| `setStart(date)`, `setEnd(date)` | Change individual boundaries |
| `setDates(start, end, options)` | Change both boundaries |
| `setAllDay(boolean)` | Change all-day status |
| `moveDates(delta)` | Shift dates |
| `remove()` | Remove from the browser instance |
| `toPlainObject()` | Get a serializable representation |
| `getResources()`, `setResources(ids)` | Premium assignment methods |

Recurring series may generate multiple Event Objects sharing one definition ID. Do not treat `getEventById()` as a complete series-management API. Use the stored source definition for editing recurrence.

The `calendar` variable and request helpers in `app.js` are closure-local. Code added outside that closure cannot access them without an explicit integration point.

## 19. Locales, styling, and printing

The locale selector supports English, Japanese, French, German, and Arabic. All locale definitions are bundled, so additional choices can be added without a network request. Arabic selection sets RTL direction; the application form labels themselves remain English.

```javascript
calendar.setOption('locale', 'ja');
calendar.setOption('direction', 'rtl');
```

Application styling lives in `public/app.css`; FullCalendar styling lives in `vendor/main.min.css`. Prefer application CSS overrides rather than modifying the vendor bundle. A CSS-only theme change does not create a Bootstrap integration; Bootstrap themes require their appropriate plugin and assets, which are not currently installed.

Print calls `window.print()` and application print styles hide navigation/editor controls. This is ordinary browser printing. The Premium optimized-print plugin is not included. Resource and timeline layouts should be checked in print preview before distributing them.

## 20. PHP API reference

### URL, sessions, and CSRF

Base request format:

```text
/api.php?action=ACTION&calendar=CALENDAR_ID&token=OPTIONAL_SHARE_TOKEN
```

Use the same-origin PHP session cookie. POST bodies must be JSON and include `X-CSRF-Token`, obtained from a prior `state` response. This applies to login and logout too. The database connection is opened before dispatching actions, so even login/state needs a working database connection.

### Endpoint table

| Action | Method | Authorization | Input / result |
| --- | --- | --- | --- |
| `state` | GET | Anonymous, owner, or valid share | Login state, CSRF, calendar, permissions |
| `login` | POST | Valid CSRF | `{password}`; owner session on success |
| `logout` | POST | Valid CSRF | Empty object; clears owner flag |
| `events` | GET | Owner or valid share | Event definition array with IDs/versions |
| `resources` | GET | Owner or valid share | Resource array |
| `save` | POST | Owner or editable share | `{id?, version?, event}` |
| `move` | POST | Owner or editable share | `{events: [...]}`; atomic moves |
| `delete` | POST | Owner or editable share | `{id, version}` |
| `calendar` | POST | Owner | `{name}`; creates calendar |
| `resource` | POST | Owner | `{resource: {...}}`; creates resource |
| `deleteResource` | POST | Owner | `{id}` |
| `shares` | GET | Owner | Share metadata, never raw tokens |
| `share` | POST | Owner | `{label, edit, expires}`; returns new token |
| `revoke` | POST | Owner | `{id}`; deletes share record |

There are no calendar rename/delete endpoints, resource update endpoint, or database user-management endpoints.

### Create request

```http
POST /api.php?action=save&calendar=1
Content-Type: application/json
X-CSRF-Token: TOKEN_FROM_STATE
Cookie: PHPSESSID=YOUR_SESSION
```

```json
{
  "event": {
    "title": "Planning",
    "start": "2026-10-12T09:00:00",
    "end": "2026-10-12T10:00:00",
    "allDay": false
  }
}
```

Success example: `{"id":12,"ok":true}`. For an update, add top-level `id` and the latest top-level `version`; do not put the version only inside event metadata.

### Move request

```json
{
  "events": [
    {
      "id": "12",
      "version": 3,
      "start": "2026-10-13T09:00:00",
      "end": "2026-10-13T10:00:00",
      "allDay": false,
      "resourceIds": ["1"]
    }
  ]
}
```

The move endpoint loads the existing payload and changes its date/all-day/resource fields. It does not replace all advanced metadata.

### Share request

```json
{
  "label": "Team schedule",
  "edit": false,
  "expires": "2026-12-31"
}
```

Share expiration means midnight UTC **at the start** of December 31. This differs from the recurrence form's inclusive final date. Use an empty expiration to avoid an expiry boundary.

### Responses and errors

| HTTP status | Meaning in this project |
| --- | --- |
| 200 | Successful operation or anonymous login-state response |
| 400 | Invalid JSON or unsupported request |
| 403 | CSRF/authentication/permission failure, expired or revoked share |
| 404 | Requested calendar does not exist |
| 409 | Stale event version or missing record during versioned write |
| 422 | Invalid event/move/share input |
| 500 | Caught database/server error; inspect PHP logs |

`state` with an invalid anonymous token returns login state rather than a 403. Reading events with that same token returns 403. Treat endpoint-specific response contents as well as HTTP status.

Save enforces a required title, a start or recurrence definition, basic date rules, HTTP(S) URLs, object-like extended properties, and a 65,536-byte encoded payload limit. It does not comprehensively validate all RRule fields, booking conflicts, resource IDs, or application business rules.

## 21. Database reference

All supplied tables use InnoDB and utf8mb4.

| Table | Important fields | Role |
| --- | --- | --- |
| `fc_calendars` | `id`, `name` | Calendar container |
| `fc_events` | `id`, `calendar_id`, `payload`, `version`, `updated_at` | One-time or recurring definition |
| `fc_resources` | `id`, `calendar_id`, `payload` | Resource definition |
| `fc_shares` | `id`, `calendar_id`, `token_hash`, `label`, `can_edit`, `expires_at`, `created_at` | Share permission |

Event/resource payloads are JSON encoded into `LONGTEXT`; they are not separate columns for every FullCalendar option. The API generates `id` from the row and injects version metadata on reads. Explicit dates are payload strings, not SQL `DATETIME` columns.

Child tables have foreign keys to calendars with `ON DELETE CASCADE`. Deleting a calendar directly in the database also deletes its events/resources/shares. The application does not provide that destructive operation as a UI action.

### Optimistic concurrency

1. GET returns event version N.
2. Editor sends N when saving or deleting.
3. SQL changes a record only when its current version matches N.
4. Save increments the version.
5. A stale operation returns 409.

Grouped moves additionally use transactions and row locking. The database concurrency safeguards do not turn the application into a live collaborative editor: other windows must refresh to receive changes.

For large deployments, consider indexed date columns for one-time events and a separate recurrence/exception model. Do not range-filter recurrence definitions solely by `dtstart`: a series beginning long before the visible month may still generate occurrences inside it.

## 22. Sharing and permissions

| Operation | Owner | Read-only share | Editable share |
| --- | --- | --- | --- |
| View events/resources | Yes | Yes | Yes |
| Event create/edit/delete | Yes | No | Yes |
| Event drag/resize | Yes | No | Yes, nonrecurring |
| Manage calendars/resources | Yes | No | No |
| Create/list/revoke shares | Yes | No | No |

Create a link in the Share calendar panel. Copy it immediately. The API creates 32 random bytes, returns their hexadecimal token, and stores only its SHA-256 hash. Raw share URLs cannot be recovered by listing shares; create another link if the original was lost.

Tokens bind access to the stored calendar. For anonymous share requests, changing the URL's `calendar` parameter does not switch access to another calendar; the token's database record determines the actual calendar.

An authenticated owner retains owner permissions even when opening a read-only share. Test read-only behavior in a private browser session or after logout.

Revoking a share stops subsequent API access, but does not erase data already loaded into someone's browser. A refresh/request exposes revocation. There is no automatic push notification to open share pages.

For another computer on the same LAN, generate links while viewing the app at the reachable host address, for example `http://192.168.1.10:4040/`. A localhost link points to the recipient's own machine. Bearer tokens can appear in browser history and server URL logs; treat them as access credentials.

## 23. Import, export, and backups

Export event JSON downloads event definitions from the currently loaded calendar. It removes database IDs and version metadata. Recurring rules remain rules rather than expanding into thousands of occurrence records.

Import accepts a JSON array and creates new records sequentially. It appends rather than merging by ID. Importing the same file twice creates duplicates. An error can stop the sequence after earlier records have already been created; import is not one transaction.

Event JSON export is not a complete database backup. It excludes calendars, resources, shares, authentication sessions, and config. Back up MariaDB separately and preserve the configuration outside public access. Restore all related tables together if IDs and share/resource references must remain consistent.

## 24. Premium licensing and Standard-only use

Standard FullCalendar views are MIT licensed. Resource and timeline views belong to Premium. The installed project loads the Scheduler bundle and therefore displays the upstream license warning while no valid Premium key is configured. Readable source does not change the license. [Official licensing](https://fullcalendar.io/license)

For Premium use, obtain the applicable key and place it in `config.php` as `scheduler_license_key`. Check that your license covers version 5.11.5. The project then supplies it to FullCalendar's `schedulerLicenseKey` option. [v5 key reference](https://legacy.fullcalendar.io/v5/schedulerLicenseKey)

For a Standard-only deployment, a complete integration change includes:

1. Load the Standard JavaScript bundle instead of Scheduler.
2. Use corresponding Standard CSS.
3. Remove Premium view choices and resource controls/options.
4. Remove or guard Premium methods such as `getResources()` and `refetchResources()` in move/receive/refresh handlers.
5. Retest interactions and database persistence.

Replacing the JavaScript filename alone is not a complete Standard-only conversion because the application's current handlers use Premium resource APIs. Database-backed sharing and the recurrence form are custom application features and can remain with Standard.

## 25. Old-browser acceptance testing

Required targets are Chrome 49 and Firefox 52. Compatibility remains unverified until the app is tested on those actual versions. ES5-style application code, local polyfills, and a successful current-Chrome test do not guarantee equivalent rendering or behavior in them.

Run the following on both targets with internet disconnected:

| Check | Expected result |
| --- | --- |
| Cold load/login | All local assets load; no script errors |
| Every configured view | Calendar renders, navigates, and resizes |
| One-time CRUD | Reload shows saved edits; deletion persists |
| Drag and resize | Dates persist; failed writes revert |
| External task drag | One new database event |
| Weekly Mon/Wed | Correct weekdays and duration |
| Two-week interval/count | Correct occurrence sequence |
| Monthly/all-day/overnight | Correct rule and display |
| End-on-date | Last day included; later starts omitted |
| Existing series edit | Form restored after reload |
| Unsupported rule | Preserve mode retains advanced recurrence |
| Read/edit share | Permissions enforced by PHP |
| Expired/revoked share | Further reads/writes denied |
| Two editing windows | Stale write reports conflict |
| Named zones and DST | Expected wall-clock/instant behavior |
| Print, locale, RTL | Usable layout |

Record browser version, action, expected/actual results, console errors, and screenshots. Older browser text/date inputs behave differently, so the form uses explicit date/time formats instead of requiring modern native pickers.

Previous generation checks passed PHP 7.4 syntax checks, MariaDB API integration tests, and installed-Chrome smoke tests with remote requests blocked. The recurrence UI tests covered weekday selection, persistence, series edits, intervals, inclusive ends, daily counts, monthly all-day events, and validation. Those results do not replace target-browser acceptance tests.

## 26. Deployment and maintenance

Deploy the full source directory and serve only `public`. Keep configuration, schema, documentation, tooling, and deployment archives above the document root. Ensure PHP can maintain sessions and Apache can read project files.

For a localhost/LAN laboratory, the supplied owner-login model provides basic access control. For an internet-facing production application, assess and implement the needed controls: password hashing/user management, HTTPS and secure session configuration, login throttling, setup-page restrictions, authorization audit logging, stronger payload/business validation, and a supported PHP runtime. The current simple laboratory does not implement those features.

Before updating FullCalendar or its dependencies:

1. Back up database and configuration.
2. Record pinned versions and license coverage.
3. Update assets/connectors consistently.
4. Keep license headers and notices.
5. Run API and interface checks.
6. Repeat Chrome 49/Firefox 52 acceptance testing.

A sample optional developer server, separate from your existing virtual host:

```powershell
php -S 127.0.0.1:8097 -t public
```

The developer tests default to port 8097. `test-api.js` accepts a base URL and creates a named test calendar that requires cleanup afterward. `test-recurrence-ui.js` removes its own isolated test calendar. Browser tests require Playwright and installed Chrome; those tools are not runtime dependencies.

## 27. Troubleshooting

| Symptom | Check or corrective action |
| --- | --- |
| Blank calendar | Browser console, HTTP status, local asset paths |
| `Cannot use import statement outside a module` | Use browser-global connector builds, not module builds |
| Database operation failed | MariaDB running, PDO MySQL, credentials, tables, PHP error log |
| Login failure | `admin_password`, not database password |
| Reload before saving | CSRF/session changed; reload and log in again |
| Event changed in another window | Refresh/reopen before retrying a stale save |
| All-day event ends too early | End must be the day after the last included date |
| Weekly event absent | Rule boundary, selected weekdays, current view dates |
| Monthly event missing in February | Rule day may not exist in that month |
| Rule opens in preserve mode | Definition is outside the interface's supported subset |
| Recurring event will not drag | Deliberate series-editing restriction |
| Wrong clock time | Calendar zone, explicit offset vs floating input, DST |
| Resource view empty | Resource records, assigned IDs, Premium assets |
| Invalid license key notice | Scheduler loaded without a valid applicable key |
| Shared page allows editing during owner test | Use an unauthenticated/private session |
| Share link fails on another computer | Replace localhost with reachable LAN host |
| Import partially completed | Sequential import stopped on a failed event; inspect existing inserts |
| Option does not persist | Advanced options are browser-session changes |

For an API problem, inspect the request URL, method, JSON body, session cookie, CSRF header, and response body. Never publish logs containing bearer tokens or configuration passwords.

## 28. Extension recipes and limitations

### Add a category selector

1. Add an HTML select to the editor template.
2. Populate it from `payload.extendedProps.category` in `openEditor`.
3. Write its value into `p.extendedProps.category` before `persist`.
4. Render category colors/classes using event hooks.
5. Add API validation if category membership matters.

No new database column is needed for optional metadata because payload JSON already stores extended properties. Add a dedicated indexed column if you need fast database category queries at scale.

### Add a resource picker

Load `resources`, build checkbox/select choices from their returned IDs and titles, and save `resourceIds`. Validate that IDs belong to the same calendar on the server. The current text field is a laboratory input, not a validated capacity-management interface.

### Implement booking conflict checks

For one-time intervals, overlap means `existing.start < new.end` and `existing.end > new.start`. Apply the correct timezone normalization, calendar/resource scope, and locking policy. Recurrence requires expansion over an explicit booking horizon and exception handling; comparing only source start dates is insufficient.

### Add per-occurrence recurrence editing

Define occurrence identity from the series and its original scheduled start. Store exclusions and replacement records transactionally. Offer “this occurrence” versus “entire series” explicitly. The current version supports series edits only.

### Current limits to retain in deployment planning

- One shared owner password; no per-user account/role system.
- No live push synchronization between open windows.
- All definitions loaded per calendar; no optimized range filtering.
- Rule-based browser recurrence, not server-generated occurrence rows.
- No dedicated UI for exception dates, yearly rules, ordinal weekdays, or multi-day recurring durations.
- No remote Google Calendar/iCalendar feeds in the offline application.
- No two-calendar event transfer interface or framework adapters.
- Resource create/delete only; no full resource-management interface.
- Browser constraints are not server booking enforcement.
- Standard browser print, not Premium optimized print.
- Premium integration currently remains enabled.
- Chrome 49/Firefox 52 acceptance still required.

For exact API details beyond these examples, use the linked v5 reference pages and inspect the locally installed unminified source matching **5.11.5**. Keep project persistence and authorization behavior in view when adding otherwise valid library features.
