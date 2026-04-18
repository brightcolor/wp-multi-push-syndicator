# Publish Wiki to GitHub

This repository includes starter wiki pages in `/wiki`.

To publish to GitHub Wiki:

1. Enable Wiki in repository settings.
2. Clone wiki repository:
   `git clone https://github.com/<owner>/<repo>.wiki.git`
3. Copy markdown files from `/wiki` into cloned wiki repo.
4. Commit and push.

Optional automation:

- Add a workflow that syncs `/wiki/*.md` to `.wiki.git` on `main` pushes.