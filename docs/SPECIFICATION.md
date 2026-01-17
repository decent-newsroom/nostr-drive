# Nostr Filesystem Library Specification

A comprehensive **library specification** for a reusable “Nostr filesystem” that implements **Drive (30042) + Folder (30045) CRUD** and supports containing the event kinds you listed:

* Index: `30040`
* AsciiDoc content: `30041`
* Markdown article: `30024`
* Markdown draft: `30023`
* Calendar: `31924`
* Calendar events: `31923` (time-based), `31922` (date-based)

## 1. Purpose and positioning

### 1.1 Goal

Provide a deterministic, testable, framework-agnostic library that models a filesystem-like hierarchy on Nostr, with:

* **Drive nodes** (`kind:30042`) that mount root folders
* **Folder nodes** (`kind:30045`) that contain references to child nodes (“files” and subfolders)
* **Child nodes** restricted to a configured allowlist of kinds (your list)

The library must support full CRUD semantics for drives and folders using Nostr’s **replaceable / addressable** event model: create and update are publish operations; “delete” is unlinking (plus optional NIP-09 deletion, outside this library’s core).

### 1.2 Non-goals

* No UI (web screens). Optional CLI belongs in a separate bundle.
* No relay networking details in core (no websocket loops, no retry strategy).
* No guarantee of global deletion (relays may ignore NIP-09).
* No Ghost theme support.

---

## 2. Terminology

* **Address (coordinate):** `"<kind>:<pubkey>:<d>"` string identifying an addressable event.
* **Drive:** `kind:30042`, mounts root folders.
* **Folder:** `kind:30045`, contains child references.
* **Node:** any addressable event that may be contained in a folder (folder or “file-kind” content).
* **Event store:** adapter responsible for fetching/publishing events to relays and returning the latest replaceable for a coordinate.
* **Owner pubkey:** the pubkey under which drives/folders are authored (enforced by policy).

---

## 3. Supported kinds and content profiles

### 3.1 Node kinds allowed inside folders (MVP allowlist)

Folders MAY contain children whose coordinates have kinds in:

* `30040` (Index)
* `30041` (AsciiDoc content)
* `30024` (Markdown article; application-defined profile)
* `30023` (Markdown draft; application-defined profile)
* `31924` (Calendar) ([GitHub][1])
* `31923` (Time-based calendar event) ([GitHub][1])
* `31922` (Date-based calendar event) ([GitHub][1])

Notes:

* The library treats `30023` / `30024` / `30040` / `30041` as **application profiles**; it does not enforce NIP-specific tag semantics beyond requiring `d`.

### 3.2 Drive and Folder invariants

* Drives (`30042`) and folders (`30045`) are addressable and **must** include a `d` tag.
* Drives and folders **must** have `content: ""` (empty string).

---

## 4. Event formats and tag conventions

### 4.1 Drive event `kind:30042`

**Required tags**

* `["d", "<drive-id>"]`
* One or more `["a", "<30045:pubkey:d>", "<relay-hint?>"]` root folder mounts (ordered)

**Optional tags**

* `["title", "<string>"]`
* `["description", "<string>"]`

**Content**

* MUST be `""`.

**Example**

```json
{
  "kind": 30042,
  "pubkey": "<owner-pubkey>",
  "created_at": 1730000000,
  "tags": [
    ["d", "dn-main-drive"],
    ["title", "DN Unfold Drive"],
    ["description", "Themes, magazines, and calendars"],
    ["a", "30045:<owner-pubkey>:themes", "wss://relay.example"],
    ["a", "30045:<owner-pubkey>:magazines", "wss://relay.example"],
    ["a", "30045:<owner-pubkey>:calendar", "wss://relay.example"]
  ],
  "content": ""
}
```

### 4.2 Folder event `kind:30045`

**Required tags**

* `["d", "<folder-id>"]`
* Zero or more membership references (`a` tags; see below)

**Optional tags**

* `["title", "<string>"]`
* `["description", "<string>"]`

**Content**

* MUST be `""`.

#### Folder membership tag (canonical)

Membership is represented by `a` tags referencing child coordinates.

Canonical forms (library MUST accept both):

1. Minimal membership

* `["a", "<kind:pubkey:d>", "<relay-hint?>"]`

2. Extended membership (performance/UX hints)

* `["a", "<kind:pubkey:d>", "<relay-hint?>", "<last-seen-event-id?>", "<name-hint?>"]`

Rules:

* `<last-seen-event-id?>` and `<name-hint?>` are advisory only.
* Order of membership tags is the default display order; not required for correctness.

**Example**

```json
{
  "kind": 30045,
  "pubkey": "<owner-pubkey>",
  "created_at": 1730000100,
  "tags": [
    ["d", "themes"],
    ["title", "Themes"],
    ["a", "30045:<owner-pubkey>:themes/default", "wss://relay.example", "", "default"]
  ],
  "content": ""
}
```

---

## 5. Library boundaries and architecture

### 5.1 Package structure (recommended)

* `decentnewsroom/nostr-filesystem` (pure library)

    * `Domain/` value objects + DTOs
    * `Service/` filesystem operations
    * `Validation/`
    * `Exception/`
    * `Contract/` interfaces

Optional later:

* `decentnewsroom/nostr-filesystem-bundle` (Symfony bundle)

    * DI wiring + CLI commands + optional controllers

### 5.2 Core interfaces (library contracts)

#### EventStoreInterface (required)

The library does not talk to relays directly. It requires an adapter:

```php
interface EventStoreInterface {
    /** Fetch latest replaceable event for an address (kind,pubkey,d). */
    public function getLatestByAddress(Address $address): ?NostrEvent;

    /** Fetch a concrete event by id (optional, for hints/verification). */
    public function getById(string $eventId): ?NostrEvent;

    /** Batch fetch latest for multiple addresses. */
    public function getLatestByAddresses(array $addresses): array; // Address => ?NostrEvent

    /** Publish an event (already signed). Returns event id or relay receipt info. */
    public function publish(NostrEvent $event): PublishResult;
}
```

#### SignerInterface (optional but recommended)

Allows the library to build unsigned events and delegate signing:

```php
interface SignerInterface {
    public function sign(array $unsignedEvent): NostrEvent;
    public function getPublicKey(): string;
}
```

If you already handle signing outside, the library may expose “builders” and accept signed events.

#### ClockInterface (optional)

For deterministic tests and reproducible timestamps.

#### CacheInterface (optional)

If present, used to cache read operations (directory hydration, mount tables). Use PSR-16 or PSR-6.

---

## 6. Domain models

### 6.1 Address

Value object representing `kind`, `pubkey`, `d`.

* `Address::parse("30045:...:themes")`
* `Address::toString()` canonicalizes formatting.

### 6.2 Drive

DTO representing:

* `address: Address` (kind 30042)
* `title?`, `description?`
* `roots: Address[]` (kind 30045 addresses)
* `rawEvent: NostrEvent`

### 6.3 Folder

DTO representing:

* `address: Address` (kind 30045)
* `title?`, `description?`
* `entries: FolderEntry[]`
* `rawEvent: NostrEvent`

### 6.4 FolderEntry

* `address: Address` (child)
* `relayHint?: string`
* `lastSeenEventId?: string`
* `nameHint?: string`

---

## 7. Operations specification

### 7.1 Drive CRUD

#### Create drive

**Input**

* `driveId (d tag)`, optional title/description
* `rootFolders: Address[]` (kind 30045 only)

**Behavior**

* Validate: all roots are kind 30045
* Build drive event with ordered `a` tags
* `content: ""`

**Output**

* Either signed `NostrEvent` (if using SignerInterface) or unsigned array.

#### Read drive

**Input**

* `driveAddress (30042:pubkey:d)`

**Behavior**

* `EventStore.getLatestByAddress`
* Validate invariants
* Parse roots from `a` tags where kind=30045
* Optionally hydrate roots (fetch folder metadata) via batch call

**Output**

* `Drive` DTO

#### Update drive

**Input**

* drive address + mutation (set roots, reorder roots, edit metadata)

**Behavior**

* Fetch latest drive
* Apply changes
* Publish new event with same `(kind,pubkey,d)`

#### Delete drive

**Library meaning**

* The library does not guarantee network deletion.
* Provide `archiveDrive()` helper that sets `["status","archived"]` (optional convention) and/or removes from host UI.
* Provide `unlinkRootFolder()` to remove mounts.

(If you want NIP-09, put it in an integration layer.)

---

### 7.2 Folder CRUD

#### Create folder

**Input**

* `folderId`, optional title/description, optional initial entries

**Behavior**

* Build `kind:30045` with `d`, empty content, membership `a` tags

#### Read folder

**Input**

* folder address

**Behavior**

* Fetch latest folder event
* Parse membership tags into `FolderEntry[]`
* Validate allowlist kinds

**Output**

* `Folder` DTO

#### Update folder

**Input**

* folder address + desired new state or patch ops

**Behavior**

* Fetch latest folder
* Apply changes
* Publish new folder event

#### Delete folder

**Library meaning**

* Unlink it from its parent (if parent known) is outside “folder-only” scope unless you maintain backlinks.
* Provide helper `removeEntry(parentFolder, childFolderAddress)` at the membership-op layer.

---

### 7.3 Folder membership operations (first-class)

These are the “filesystem primitives.” They operate by creating updated `30045` events.

#### addEntry(folder, childEntry)

* Validate child kind is allowed
* Add `a` tag; if already present, no-op unless “replace hints” requested

#### removeEntry(folder, childAddress)

* Remove all membership tags that match that address

#### moveEntry(srcFolder, dstFolder, childAddress)

* removeEntry(src)
* addEntry(dst)
* Publish both updated folders (order of operations is integration-defined)

#### reorderEntries(folder, orderedChildAddresses)

* Ensure set matches current entries (or specify policy: partial reorder allowed)
* Emit updated event with tags ordered accordingly

#### setEntries(folder, entries[])

* Replace membership list entirely

---

### 7.4 Containment constraints (allowlist)

On any operation that adds/sets entries, enforce:

* Child kind ∈ allowlist
* Child address must be parseable and include `d`
* If child kind is `30045`, it is a folder (allowed nesting)

Library must expose configuration:

* default allowlist (your list)
* override allowed kinds (for other deployments)

---

## 8. Consistency and merge policy

Because folders are replaceable events, concurrent edits can clobber each other.

### 8.1 Required: optimistic merge helper

Library should provide a `FolderMerger` utility:

* Inputs:

    * base folder event (the one user edited)
    * latest folder event (current head)
    * intended ops (add/remove/reorder)
* Output:

    * merged membership list applying ops on top of latest when possible

### 8.2 Conflict rules (MVP)

* add/remove are commutative: apply to latest
* reorder conflicts if the referenced set changed; in that case:

    * either reject with conflict exception, or
    * apply reorder to intersection and append new entries at end (recommended default)

Expose this as a strategy interface so DN can choose.

---

## 9. Path layer (optional but strongly recommended)

To support “filesystem-like organization,” provide an optional path API on top of folder IDs.

### 9.1 Path conventions

* Folder “name” defaults to its `d`
* Child “name” defaults to child `d`
* If `nameHint` exists in membership tag, it may be used as the display name, but `d` remains canonical for addressing.

### 9.2 Required path functions (optional module)

* `resolvePath(Drive, "/themes/default") -> Address`
* `listPath(Drive, "/themes") -> FolderEntry[]`

This requires a traversal strategy:

* Drive mounts roots by folder `d`
* Each folder contains child folders by their `d`
* Ambiguity (two children with same `d`) must error deterministically.

---

## 10. Validation and error model

### 10.1 Validation rules

* Drive:

    * `d` tag required
    * `content == ""`
    * roots are only `a` tags to kind `30045`
* Folder:

    * `d` tag required
    * `content == ""`
    * membership tags `a` parse to valid addresses
    * child kind allowed

### 10.2 Exceptions (recommended)

* `InvalidEventException`
* `NotFoundException`
* `DisallowedKindException`
* `ConflictException` (merge/reorder)
* `UnauthorizedException` (if policy layer enabled)

---

## 11. Authorization and policy hooks

Library should be neutral but allow host enforcement.

### 11.1 Policy interface

```php
interface AuthorizationPolicyInterface {
    public function canWriteDrive(string $actorPubkey, Address $drive): bool;
    public function canWriteFolder(string $actorPubkey, Address $folder): bool;
}
```

DN can implement policies (e.g., only owner pubkey can mutate; later ACL).

---

## 12. Caching and performance

### 12.1 Cacheable reads

* Drive read: cache latest drive event for TTL (or keyed by event id)
* Folder read: cache latest folder event
* Optional: cache hydrated titles for children (batch fetch + cache)

### 12.2 Cache keying

* `drive.latest.<address>`
* `folder.latest.<address>`
* `folder.children.<address>`
* if relay sets matter in your adapter, include a relay-hash in keys at integration level

---

## 13. Test plan (mandatory for “credible” agent output)

### 13.1 Fixture set

Provide JSON fixtures for:

* a drive with roots
* folders with nested folders
* folders containing each supported child kind
* invalid folder containing disallowed kind
* reorder conflict scenario

### 13.2 Unit tests (required)

* address parsing/formatting roundtrip
* drive parse/build
* folder parse/build
* add/remove/move/reorder
* allowlist enforcement
* merge behavior under concurrent edits

---

## 14. Integration guidance for DN and Unfold

### 14.1 DN integration (admin CRUD)

DN supplies:

* EventStore adapter backed by your relay pool / signer
* UI for drive/folder CRUD and membership management

Library provides:

* deterministic tag building/parsing
* merge helpers
* allowlist enforcement

### 14.2 Unfold integration (read-only runtime)

Unfold needs:

* read drive → traverse to `/unfold/config.json` or to a known folder path
* read templates/theme tokens from contained nodes
* read magazine index `30040` and content nodes

Unfold should rely on the library’s path/traversal module (if implemented), or directly read by folder IDs.

---

## 15. Versioning and extensibility

### 15.1 SemVer

* v0.x: API may change quickly
* v1.0: freeze core interfaces (`EventStoreInterface`, `DriveService`, `FolderService`, `Address`)

### 15.2 Planned extensions (not in MVP)

* symlinks (`30044`) and tracebacks (`30043`)
* filesystem permissions/ACL semantics
* directory indexing acceleration (precomputed maps)

