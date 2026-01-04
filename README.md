# Personal Tutor

小学生・中学生向けの家庭教師型学習アプリです。教科・単元・学習コンテンツを JSON で管理し、教材を見ながら疑問点を OpenAI API を利用した家庭教師に質問できます。

## 主な機能

- 教科選択と単元選択
- 学習コンテンツの閲覧（説明資料・問題集）
- OpenAI API と連携した質問チャット（API キー未設定時は教材に基づくデモ応答）

## 動作環境

- PHP 8 以上を推奨（`json`・`curl` 拡張が必要）

## セットアップ

1. リポジトリを取得し、プロジェクトルートへ移動します。
2. OpenAI API を利用する場合は、環境変数 `OPENAI_API_KEY` に API キーを設定します。

```bash
export OPENAI_API_KEY="sk-..."
```

3. PHP のビルトインサーバーでアプリを起動します。

```bash
php -S localhost:8000 -t public
```

4. ブラウザで `http://localhost:8000/` を開き、学習を開始します。

## データ構造

学習コンテンツは `data/grades` 配下で学年フォルダごとに管理されています。構造は次の通りです。

```
data/grades/
├── elementary-5/
│   ├── grade.json        ... 学年のメタデータ (id/name/description)
│   ├── math.json         ... 教科ごとの教材 (ファイル名が教科 id に相当)
│   └── science.json      ... 教科ごとの教材
├── middle-1/
│   └── grade.json
└── middle-2/
    └── grade.json
```

各教科ファイルの JSON 形式は以下の通りです。

```jsonc
{
  "id": "math",           // 任意。未指定ならファイル名が id になります
  "name": "算数・数学",
  "description": "教科の説明",
  "units": [
    {
      "id": "fractions-basics",
      "name": "分数のきほん",
      "grade": "対象学年表示用の文言",
      "overview": "単元の概要",
      "goals": ["めあて1", "めあて2"],
      "explanation": "HTML 文字列",
      "exercises": [
        {
          "title": "問題タイトル",
          "question": "問題文",
          "hint": "ヒント",
          "answer": "解答"
        }
      ]
    }
  ]
}
```

コンテンツを追加・更新する場合は、学年フォルダに `grade.json` を用意し、教科ごとの JSON ファイルを追加してください。

## 備考

- OpenAI API が利用できない環境では、教材内容をまとめたヒントが表示されます。
- 送信済みの会話履歴はブラウザ上で保持され、以降の質問時にコンテキストとして利用されます。
