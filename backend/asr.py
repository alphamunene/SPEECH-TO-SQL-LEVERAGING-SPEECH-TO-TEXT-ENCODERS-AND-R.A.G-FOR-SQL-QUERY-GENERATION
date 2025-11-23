# Simple wrapper for whisper transcription
import whisper, os
model = None
def get_model():
    global model
    if model is None:
        model = whisper.load_model("base", device="cpu")
    return model

def transcribe(path):
    m = get_model()
    result = m.transcribe(path)
    return result.get('text','')
