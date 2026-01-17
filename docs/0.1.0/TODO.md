## 1) The biggest mismatch: folder entries should be coordinates-first

You currently model folder entries primarily by **event id** (`e` tags) with extra fields for kind and position, and only sometimes add an `a` tag.

For your intended contained kinds:

* `30040, 30041, 30023, 30024, 31922, 31923, 31924`

these are all **addressable / parameterized replaceable** kinds (3xxxx / 319xx patterns). In practice you want directory membership to be **stable across updates**, which means:

* Membership should be represented by **`a` tags (coordinates)** as the canonical link.
* You should not require event ids for membership. Event ids are ephemeral for replaceable content.

### Recommendation (strong)

**Directory membership tags = `a` tags only**, with optional relay hint and optional name hint, e.g.:

* Minimal: `["a", "<kind:pubkey:d>", "<relay-hint?>"]`
* Extended: `["a", "<kind:pubkey:d>", "<relay-hint?>", "<name-hint?>"]`

Then ordering is simply the tag order. No explicit “position” field needed.

If you do want to support non-addressable regular events later, add `e` support as an optional feature, but keep the MVP clean.

---

## 2) Drive events should actually “mount” roots

Your README’s Drive example omits the defining behavior: root folder mounts. A Drive (30042) should include ordered `a` tags pointing to root `30045` directories.

### Fix the Drive spec in README

Include something like:

```json
{
  "kind": 30042,
  "pubkey": "author_pubkey",
  "created_at": 1234567890,
  "content": "",
  "tags": [
    ["d", "drive-identifier"],
    ["title", "My Drive"],
    ["a", "30045:author_pubkey:themes", "wss://relay.example"],
    ["a", "30045:author_pubkey:magazines", "wss://relay.example"],
    ["a", "30045:author_pubkey:calendar", "wss://relay.example"]
  ]
}
```

(Use `title`/`description` rather than `name` unless you have a strong reason—your earlier events and common conventions lean that way.)

---

## 3) Address model: avoid “pubkey OR coordinate” ambiguity

Your README says:

> Address: can be either a pubkey or a coordinate…

That ambiguity will infect every interface and every consumer.

### Recommendation

Split it into two types:

* `Pubkey` (hex string validation, normalization)
* `Coordinate` / `Address` (kind + pubkey + d)

Then your folder entries and most operations use `Coordinate` exclusively.

If you must keep one class, make it explicitly represent a coordinate, and do not overload it as “sometimes pubkey.”

---

## 4) EventStoreInterface signatures are currently awkward

Right now you have:

* `getLatestByAddress(Address $address, int $kind, ?string $identifier = null)`

But an addressable coordinate already contains `(kind, pubkey, d)`—passing kind separately invites inconsistencies.

### Recommendation

Define a value object (coordinate) and use it consistently:

* `getLatestByCoordinate(Coordinate $coord): ?Event`
* `getLatestByCoordinates(Coordinate[] $coords): array` (keyed by coord string)
* `getById(string $id): ?Event` (optional)
* `publish(Event $event): PublishResult` (not just `bool`)

Also: don’t return `?array` for events if you can avoid it. Even a minimal `Event` DTO (id, kind, pubkey, created_at, tags, content, sig) will make downstream code dramatically safer.

---

## 5) Folder entry schema: remove custom `e`-tag fields

This in your README:

```json
["e", "event_id_1", "", "30040", "0"]
```

is not a standard encoding and will surprise anyone who expects Nostr tag conventions (and it will complicate interoperability). If you switch to `a`-only membership, this disappears entirely.

If you later support `e`, keep it conventional:

* `["e", "<event_id>", "<relay-hint?>", "<marker?>"]`

and store kind/position in your own higher-level model, not inside the `e` tag.

---

## 6) CRUD semantics: “delete” should mean unlink + optional tombstone

Because these are replaceable events, “delete” is not reliably enforceable at network level. The library should define delete as:

* remove membership link from the parent directory (publish updated parent `30045`)
* optionally mark the deleted node with a status tag like `["status","archived"]`

If you want NIP-09 deletions, that belongs in an integration layer (or an optional module), not as a core guarantee.

---

## 7) Calendar kinds: keep allowlisting, do not over-interpret yet

Allowlisting `31922/31923/31924` is fine. Do **not** bake calendar field semantics into the filesystem library. Treat them as nodes with coordinates and leave interpretation to the consuming app (DN/Unfold/Calendar UI).

---

## 8) What I would change in the README immediately

### Replace these ideas:

* “FolderEntry supports event IDs and coordinates”
* “Entries use both e and a tags”
* “Delete drive/folder”

### With:

* “Folders contain **coordinates** (`a` tags) to allowed node kinds”
* “Ordering is the order of `a` tags”
* “Delete is unlinking and optional archiving”

Also, rename “App-specific events” in KindValidator: the calendar kinds are standard-ish (NIP-52), while your file profiles are your application concern.

---

## 9) Minimal v0.1 API that will stay stable

If you want a clean, credible core:

### Domain

* `Coordinate` (kind, pubkey, d)
* `Drive` (coordinate + roots)
* `Folder` (coordinate + entries)
* `FolderEntry` (coordinate + relayHint? + nameHint?)

### Services

* `DriveService`

    * `create(Coordinate $drive, array $rootFolders, Meta $meta): Event`
    * `get(Coordinate $drive): Drive`
    * `setRoots(Coordinate $drive, array $roots): Event`
* `FolderService`

    * `create(Coordinate $folder, Meta $meta): Event`
    * `get(Coordinate $folder): Folder`
    * `add(Coordinate $folder, FolderEntry $entry): Event`
    * `remove(Coordinate $folder, Coordinate $child): Event`
    * `reorder(Coordinate $folder, Coordinate[] $orderedChildren): Event`

### Store

* `EventStoreInterface` (fetch/publish)

This is enough to import into DN and Unfold without rework.

---

## 10) Immediate next step

Before adding features, I recommend you adjust the repo scaffold to:

1. Make folder membership **`a`-only** for your allowed kinds.
2. Change `Address` into a coordinate type (or split Pubkey/Coordinate).
3. Fix README event examples to reflect drive mounts and coordinate membership.
4. Add PHPUnit fixtures that assert:

    * parsing drive roots from `a` tags
    * add/remove/reorder produces the correct tag arrays

If you paste the current `src/Domain/*` and `src/Service/*` classes (or even just `Address`, `FolderEntry`, and `FolderService::addEntry()`), I will give you a precise diff-style refactor plan aligned with the above, including the exact tag array shapes to output.
