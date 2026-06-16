from fastapi.testclient import TestClient

from app.main import app

client = TestClient(app)


def test_health() -> None:
    res = client.get("/health")
    assert res.status_code == 200
    assert res.json()["service"] == "ai-worker"


def test_infer_intent() -> None:
    res = client.post("/infer/intent", json={"question": "qual a selic atual?"})
    assert res.status_code == 200
    assert res.json()["type"] == "indicator_value"


def test_infer_explain() -> None:
    res = client.post(
        "/infer/explain",
        json={
            "intent": {"type": "indicator_value", "params": {}},
            "result": {"type": "indicator_value", "indicator": "selic", "value": 10.5},
        },
    )
    assert res.status_code == 200
    assert "10.5" in res.json()["answer"]
