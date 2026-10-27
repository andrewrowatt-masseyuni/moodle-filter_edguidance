# Teacher guidance filter (filter_edguidance)

Part of **teacher guidance** - see `local/edguidance/README.md` for the design, which covers all
three plugins.

Replaces each embedded guidance token -
`<div class="edguidance-embed" data-edguidance="KEY"></div>` - with the guidance block, for anyone
holding `local/edguidance:view` in the activity (or, for a section summary, the course), and with
nothing for everyone else. In a course context a token resolves to any of that course's section
blocks, because a filter is not told which section it is formatting. A token anywhere else - the
front page, a category, a block - is removed, as is one whose key does not belong here; editors see
a short notice for the second case, so they can fix a token pasted in from elsewhere.

The filter installs disabled, as every filter does: switch it **On** under *Site administration >
Plugins > Filters > Manage filters*, where it joins the end of the filter order. Without it no one
sees any guidance. Keep it last: the guidance it inserts has already been through the filters, and
any filter after it would run over that text a second time. Switching it off in a course hides
guidance there from teachers too, but exposes nothing to students - the guidance was never in the
text.

Output depends on who is looking, which is safe because Moodle 4.5 has no text cache. Text that is
cleaned before filtering (under `$CFG->forceclean`, say) loses the token's `data-` attribute and shows
no guidance: that fails closed.

Requires `local_edguidance`. Moodle 4.5 only. GNU GPL v3 or later.
