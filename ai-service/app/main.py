from fastapi import FastAPI
app=FastAPI(title="LifePilot AI Service",version="0.1.0",description="P1 service scaffold. AI capabilities are intentionally not implemented.")
@app.get("/health")
def health(): return {"service":"lifepilot-ai-service","status":"ok","ai_enabled":False}
