# ブランチを作成して、リモートにプッシュする

name=$0
git branch feature/$name
git checkout feature/$name
git push --set-upstream origin feature/$name
git checkout master