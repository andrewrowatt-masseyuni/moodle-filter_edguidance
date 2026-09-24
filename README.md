# Teacher guidance filter (filter_edguidance)

Part of **teacher guidance** - see `local/edguidance/README.md` for the design, which covers all
three plugins.

Replaces each embedded guidance token -
`<div class="edguidance-embed" data-edguidance="KEY"></div>` - with the guidance block, for anyone
holding `local/edguidance:view` in the activity, and with nothing for everyone else. A token outside
an activity, or one whose key does not belong to this activity, is removed; editors see a short
notice for the second case, so they can fix a token pasted in from elsewhere.

The filter switches itself on site-wide when installed, at the end of the filter order. It must stay
**On**: without it no one sees any guidance. Switching it off in a course hides guidance there from
teachers too, but exposes nothing to students - the guidance was never in the text.

Output depends on who is looking, which is safe because Moodle 4.5 has no text cache. Text that is
cleaned before filtering (under `$CFG->forceclean`, say) loses the token's `data-` attribute and shows
no guidance: that fails closed.

Requires `local_edguidance`. Moodle 4.5 only. GNU GPL v3 or later.
