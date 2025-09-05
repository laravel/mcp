- Verify file by file if i am happy with the code.
- `handle` -> resolve from the IOC.
- ValidationException -> needs to return ToolError
- test make commands at unit level + test them on mcp-demo -> plus built a feature them
- 

Example: You want the AI to make a schedule for tomorrow

Using a Prompt:

You type:

“Make me a schedule for tomorrow with time blocks for work, lunch, and exercise.”

The AI replies with a text-based plan like:
•	9:00–12:00: Work
•	12:00–1:00: Lunch
•	6:00–7:00: Exercise

This is just generated text — nothing changes in your real calendar.

⸻

Using a Tool (through MCP):

The AI calls a Calendar tool and actually creates events:
•	9:00–12:00: “Work” added to Google Calendar
•	12:00–1:00: “Lunch” added
•	6:00–7:00: “Exercise” added

Here, the AI isn’t just describing — it’s acting on an external system.

⸻

✅ Key difference:
•	Prompt = “Tell me” → AI generates words.
•	Tool = “Do this” → AI performs actions via APIs.
